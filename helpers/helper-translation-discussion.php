<?php
/**
 * Helper that manages and shows the discussions
 *
 * @package gp-translation-helpers
 * @since 0.0.1
 */
class Helper_Translation_Discussion extends GP_Translation_Helper {

	/**
	 * Helper priority.
	 *
	 * @since 0.0.1
	 * @var int
	 */
	public $priority = 0;

	/**
	 * Helper title.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	public $title = 'Discussion';

	/**
	 * Indicates whether the helper loads asynchronous content or not.
	 *
	 * @since 0.0.1
	 * @var bool
	 */
	public $has_async_content = true;

	/**
	 * The post type used to store the comments.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	const POST_TYPE = 'gth_original';

	/**
	 * The comment post status. Creates it as published.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	const POST_STATUS = 'publish';

	/**
	 * The taxonomy key.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	const LINK_TAXONOMY = 'gp_original_id';

	/**
	 *
	 * @since 0.0.1
	 * @var string
	 */
	const URL_SLUG = 'discuss';

	/**
	 *
	 * @since 0.0.1
	 * @var string
	 */
	const ORIGINAL_ID_PREFIX = 'original-';

	/**
	 * Registers the post type, its taxonomy, the comments' metadata and adds a filter to moderate the comments.
	 *
	 * Method executed just after the constructor.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function after_constructor() {
		$this->register_post_type_and_taxonomy();
		add_filter( 'pre_comment_approved', array( $this, 'comment_moderation' ), 10, 2 );
		add_filter( 'map_meta_cap', array( $this, 'map_comment_meta_caps' ), 10, 4 );
		add_filter( 'user_has_cap', array( $this, 'give_user_read_cap' ), 10, 3 );
		add_filter( 'post_type_link', array( $this, 'rewrite_original_post_type_permalink' ), 10, 2 );
	}

	/**
	 * Registers the post type with its taxonomy and the comments' metadata.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function register_post_type_and_taxonomy() {
		register_taxonomy(
			self::LINK_TAXONOMY,
			array(),
			array(
				'public'  => false,
				'show_ui' => false,
				'rewrite' => false,
			)
		);

		$post_type_args = array(
			'supports'          => array( 'comments' ),
			'show_ui'           => false,
			'show_in_menu'      => false,
			'show_in_admin_bar' => false,
			'show_in_nav_menus' => false,
			'can_export'        => false,
			'has_archive'       => false,
			'show_in_rest'      => true,
			'taxonomies'        => array( self::LINK_TAXONOMY ),
			'rewrite'           => false,
		);

		register_post_type( self::POST_TYPE, $post_type_args );

		register_meta(
			'comment',
			'translation_id',
			array(
				'description'       => 'Translation that was displayed when the comment was posted',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( $this, 'sanitize_translation_id' ),
			)
		);

		register_meta(
			'comment',
			'locale',
			array(
				'description'       => 'Locale slug associated with a string comment',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( $this, 'sanitize_comment_locale' ),
				'rewrite'           => false,
			)
		);

		register_meta(
			'comment',
			'comment_topic',
			array(
				'description'       => 'Reason for the comment',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( $this, 'sanitize_comment_topic' ),
				'rewrite'           => false,
			)
		);
	}

	/**
	 * Give subscribers permission to add our comment metas.
	 *
	 * @param      array  $caps     The capabilities they need to have.
	 * @param      string $cap      The capability we're testing for.
	 * @param      int    $user_id  The user id.
	 * @param      array  $args     Other arguments.
	 *
	 * @return     array  The capabilities they need to have.
	 */
	public function map_comment_meta_caps( $caps, $cap, $user_id, $args ) {
		if ( 'edit_comment_meta' === $cap && isset( $args[1] ) && in_array( $args[1], array( 'translation_id', 'locale', 'comment_topic' ), true ) ) {
			return array( 'read' );
		}
		return $caps;
	}

	/**
	 * Ensure that a user has the read capability on translate.wordpress.org.
	 *
	 * @param      array $allcaps  All capabilities of the uer.
	 * @param      array $caps     The capabilities requested.
	 * @param      array $args     Other arguments.
	 *
	 * @return     array  Potentially modified capabilities of the user.
	 */
	public function give_user_read_cap( $allcaps, $caps, $args ) {
		if ( ! defined( 'WPORG_TRANSLATE_BLOGID' ) || get_current_blog_id() !== WPORG_TRANSLATE_BLOGID ) {
			return $allcaps;
		}

		if ( in_array( 'read', $cap, true ) && is_user_logged_in() && ! is_admin() ) {
			$allcaps['read'] = true;
		}

		return $allcaps;
	}

	/**
	 * Gets the permalink and stores in the cache.
	 *
	 * @since 0.0.2
	 *
	 * @param string  $post_link The post's permalink.
	 * @param WP_Post $post      The post in question.
	 *
	 * @return mixed|string
	 */
	public function rewrite_original_post_type_permalink( string $post_link, WP_Post $post ) {
		static $cache = array();

		if ( self::POST_TYPE !== $post->post_type ) {
			return $post_link;
		}

		if ( isset( $cache[ $post->ID ] ) ) {
			return $cache[ $post->ID ];
		}

		// Cache the error case and overwrite it later if we succeed.
		$cache[ $post->ID ] = $post_link;

		$original_id = self::get_original_from_post_id( $post->ID );
		if ( ! $original_id ) {
			return $cache[ $post->ID ];
		}

		$original = GP::$original->get( $original_id );
		if ( ! $original ) {
			return $cache[ $post->ID ];
		}

		$project = GP::$project->get( $original->project_id );
		if ( ! $project ) {
			return $cache[ $post->ID ];
		}

		// We were able to gather all information, let's put it in the cache.
		$cache[ $post->ID ] = GP_Route_Translation_Helpers::get_permalink( $project->path, $original_id );

		return $cache[ $post->ID ];
	}

	/**
	 * Updates the comment's approval status before it is set.
	 *
	 * It only updates the approved status if the user has previous translations.
	 *
	 * @since 0.0.1
	 *
	 * @param int|string|WP_Error $approved    The approval status. Accepts 1, 0, 'spam', 'trash',
	 *                                         or WP_Error.
	 * @param array               $commentdata Comment data.
	 *
	 * @return bool|int|string|WP_Error|null
	 */
	public function comment_moderation( $approved, array $commentdata ) {
		global $wpdb;

		// If the comment is already approved, we're good.
		if ( $approved ) {
			return $approved;
		}

		// We only care on comments on our specific post type.
		if ( self::POST_TYPE !== get_post_type( $commentdata['comment_post_ID'] ) ) {
			return $approved;
		}

		// We can't do much if the comment was posted logged out.
		if ( empty( $commentdata['comment_author'] ) ) {
			return $approved;
		}

		// If our user has already contributed translations, approve comment.
		$user_current_translations = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->gp_translations WHERE user_id = %s AND status = 'current'", $commentdata['comment_author'] ) );
		if ( $user_current_translations ) {
			$approved = true;
		}

		return $approved;
	}

	/**
	 * Gets the slug for the post ID.
	 *
	 * @since 0.0.1
	 *
	 * @param int $post_id  The post ID.
	 *
	 * @return false|string
	 */
	public static function get_original_from_post_id( int $post_id ) {
		$terms = wp_get_object_terms( $post_id, self::LINK_TAXONOMY, array( 'number' => 1 ) );
		if ( empty( $terms ) ) {
			return false;
		}

		return $terms[0]->slug;
	}

	/**
	 * Gets the post id for the comments and stores it in the cache.
	 *
	 * @since 0.0.1
	 *
	 * @param int $original_id  The original id for the string to translate. E.g. "2440".
	 *
	 * @return int|WP_Error
	 */
	public static function get_shadow_post( int $original_id ) {
		$cache_key = self::LINK_TAXONOMY . '_' . $original_id;

		if ( true || false === ( $post_id = wp_cache_get( $cache_key ) ) ) {
			$gp_posts = get_posts(
				array(
					'tax_query'        => array(
						array(
							'taxonomy' => self::LINK_TAXONOMY,
							'terms'    => $original_id,
							'field'    => 'slug',
						),
					),
					'post_type'        => self::POST_TYPE,
					'posts_per_page'   => 1,
					'post_status'      => self::POST_STATUS,
					'suppress_filters' => false,
				)
			);

			if ( ! empty( $gp_posts ) ) {
						$post_id = $gp_posts[0]->ID;
			} else {
				$post_id = wp_insert_post(
					array(
						'post_type'      => self::POST_TYPE,
						'tax_input'      => array(
							self::LINK_TAXONOMY => array( strval( $original_id ) ),
						),
						'post_status'    => self::POST_STATUS,
						'post_author'    => 0,
						'comment_status' => 'open',
					)
				);
			}
		}

		wp_cache_add( $cache_key, $post_id );
		return $post_id;
	}

	/**
	 * Gets the comments for the post.
	 *
	 * @since 0.0.1
	 *
	 * @return array
	 */
	public function get_async_content(): array {
		return get_comments(
			array(
				'post_id'            => self::get_shadow_post( $this->data['original_id'] ),
				'status'             => 'approve',
				'type'               => 'comment',
				'include_unapproved' => array( get_current_user_id() ),
			)
		);
	}

	/**
	 * Shows the discussion template with the comment form.
	 *
	 * @since 0.0.1
	 *
	 * @param array $comments   The comments to display.
	 *
	 * @return false|string
	 */
	public function async_output_callback( array $comments ) {
		// Remove comment likes for now (or forever :) ).
		remove_filter( 'comment_text', 'comment_like_button', 12 );

		// Disable subscribe to posts.
		add_filter( 'option_stb_enabled', '__return_false' );

		// Disable subscribe to comments for now.
		add_filter( 'option_stc_disabled', '__return_true' );

		// Link comment author to WordPress.org profile.
		add_filter(
			'get_comment_author_link',
			function() {
					$comment_author = get_comment_author();
					return '<a href="https://profiles.wordpress.org/' . $comment_author . '">' . $comment_author . '</a>';
			}
		);

		$output = gp_tmpl_get_output(
			'translation-discussion-comments',
			array(
				'comments'             => $comments,
				'post_id'              => self::get_shadow_post( $this->data['original_id'] ),
				'translation_id'       => isset( $this->data['translation_id'] ) ? $this->data['translation_id'] : null,
				'locale_slug'          => $this->data['locale_slug'],
				'original_permalink'   => $this->data['permalink'],
				'original_id'          => $this->data['original_id'],
				'project'              => $this->data['project'],
				'translation_set_slug' => $this->data['translation_set_slug'],

			),
			$this->assets_dir . 'templates'
		);
		return $output;
	}

	/**
	 * Gets the content/string to return when a helper has no results.
	 *
	 * @since 0.0.1
	 *
	 * @return false|string
	 */
	public function empty_content() {
		return $this->async_output_callback( array() );
	}

	/**
	 * Gets additional CSS required by the helper.
	 *
	 * @since 0.0.1
	 *
	 * @return bool|string
	 */
	public function get_css() {
		return file_get_contents( $this->assets_dir . 'css/translation-discussion.css' );
	}

	/**
	 * Gets additional JavaScript required by the helper.
	 *
	 * @since 0.0.1
	 *
	 * @return bool|string
	 */
	public function get_js() {
		return file_get_contents( $this->assets_dir . 'js/translation-discussion.js' );
	}

	/**
	 * Sets the comment_topic meta_key as "unknown" if is not in the accepted values.
	 *
	 * Used as sanitize callback in the register_meta for the "comment" object type,
	 * 'comment_topic' meta_key
	 *
	 * @since 0.0.2
	 *
	 * @param string $comment_topic The meta_value for the meta_key "comment_topic".
	 *
	 * @return string
	 */
	public function sanitize_comment_topic( string $comment_topic ): string {
		if ( ! in_array( $comment_topic, array( 'typo', 'context', 'question' ), true ) ) {
			$comment_topic = 'unknown';
		}
		return $comment_topic;

	}

	/**
	 * Sets the comment_topic meta_key as empty ("") if is not in the accepted values.
	 *
	 * Used as sanitize callback in the register_meta for the "comment" object type,
	 * "locale" meta_key
	 *
	 * @since 0.0.2
	 *
	 * @param string $comment_locale     The meta_value for the meta_key "locale".
	 *
	 * @return string
	 */
	public function sanitize_comment_locale( string $comment_locale ): string {
		$gp_locales     = new GP_Locales();
		$all_gp_locales = array_keys( $gp_locales->locales );

		if ( ! in_array( $comment_locale, $all_gp_locales ) ) {
			$comment_locale = '';
		}
		return $comment_locale;
	}

	/**
	 * Kills WordPress execution and displays HTML page with an error message if the translation id is incorrect.
	 *
	 * Used as sanitize callback in the register_meta for the "comment" object type,
	 * "locale" meta_key
	 *
	 * The string type (input and output) is because it is the type if $translation_id is empty.
	 *
	 * @since 0.0.2
	 *
	 * @param int|string $translation_id   The id for the translation showed when the comment was made.
	 *
	 * @return int|string
	 */
	public function sanitize_translation_id( $translation_id ) {
		if ( ! is_numeric( $translation_id ) ) {
			if ( $translation_id > 0 && ! GP::$translation->get( $translation_id ) ) {
				wp_die( 'Invalid translation ID' );
			}
		}
		return $translation_id;
	}
}

	/**
	 * Gets the slug for the post ID.
	 *
	 * @since 0.0.1
	 *
	 * @param int $post_id  The id of the post.
	 *
	 * @return false|string
	 */
function gth_discussion_get_original_id_from_post( int $post_id ) {
	return Helper_Translation_Discussion::get_original_from_post_id( $post_id );
}

	/**
	 * Callback for the wp_list_comments() function in the helper-translation-discussion.php template.
	 *
	 * @since 0.0.1
	 *
	 * @param WP_Comment $comment   The comment object.
	 * @param array      $args      Formatting options.
	 * @param int        $depth     The depth of the new comment.
	 *
	 * @return void
	 */
function gth_discussion_callback( WP_Comment $comment, array $args, int $depth ) {
	$GLOBALS['comment'] = $comment;// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

	$comment_locale = get_comment_meta( $comment->comment_ID, 'locale', true );
	$current_locale = $args['locale_slug'];

	$current_translation_id  = $args['translation_id'];
	$comment_translation_id  = get_comment_meta( $comment->comment_ID, 'translation_id', true );
	$is_a_rejection_feedback = false;
	$reject_reason           = get_comment_meta( $comment->comment_ID, 'reject_reason', true );
	if ( ! empty( $reject_reason ) && ( $current_locale && $current_locale === $comment_locale ) ) {
		$is_a_rejection_feedback = true;
	}
	?>
	<li class="<?php echo esc_attr( 'comment-locale-' . $comment_locale ); ?>" data-rejection-feedback="<?php echo $is_a_rejection_feedback ? 'true' : 'false'; ?>">
	<article id="comment-<?php comment_ID(); ?>" class="comment">
	<div class="comment-avatar">
	<?php echo get_avatar( $comment, 25 ); ?>
	</div><!-- .comment-avatar -->
	<?php printf( '<cite class="fn">%s</cite>', get_comment_author_link( $comment->comment_ID ) ); ?>
	<?php
	// Older than a week, show date; otherwise show __ time ago.
	if ( current_time( 'timestamp' ) - get_comment_time( 'U' ) > 604800 ) {
		/* translators: 1: Date , 2: Time */
		$time = sprintf( _x( '%1$s at %2$s', '1: date, 2: time' ), get_comment_date(), get_comment_time() );
	} else {
		/* translators: Human readable time difference */
		$time = sprintf( __( '%1$s ago' ), human_time_diff( get_comment_time( 'U' ), current_time( 'timestamp' ) ) );
	}
	echo '<time datetime=" ' . get_comment_time( 'c' ) . '">' . esc_html( $time ) . '</time>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
	<?php if ( $comment_locale ) : ?>
	<div class="comment-locale">Locale:
				<?php if ( ! $current_locale ) : ?>
					<a href="<?php echo esc_attr( $comment_locale . '/default' ); ?>"><?php echo esc_html( $comment_locale ); ?></a>
				<?php elseif ( $current_locale && $current_locale !== $comment_locale ) : ?>
					<a href="<?php echo esc_attr( '../../' . $comment_locale . '/default' ); ?>"><?php echo esc_html( $comment_locale ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $comment_locale ); ?>
	<?php endif; ?>
	</div>
	<?php endif; ?>
	<div class="comment-content" dir="auto">
		<?php comment_text(); ?>
		<?php if ( $reject_reason ) : ?>
		<p>
			<?php echo esc_html( _n( 'Rejection Reason: ', 'Rejection Reasons: ', count( $reject_reason ) ) ); ?>
			<span><?php echo wp_kses( implode( '</span> | <span>', $reject_reason ), array( 'span' => array() ) ); ?></span>
		</p>
		<?php endif; ?>
	</div>
	<footer>
	<div class="comment-author vcard">
			<?php
			if ( $comment->comment_parent ) {
				printf(
					'<a href="%1$s">%2$s</a>',
					esc_url( get_comment_link( $comment->comment_parent ) ),
					/* translators: The author of the current comment */
					sprintf( esc_attr( __( 'in reply to %s' ) ), esc_html( get_comment_author( $comment->comment_parent ) ) )
				);
			}

			add_filter(
				'comment_reply_link',
				function( $link, $args, $comment, $post ) {
					$data_attributes = array(
						'commentid'      => $comment->comment_ID,
						'postid'         => $post->ID,
						'belowelement'   => $args['add_below'] . '-' . $comment->comment_ID,
						'respondelement' => $args['respond_id'],
						'replyto'        => sprintf( $args['reply_to_text'], $comment->comment_author ),
					);

					$data_attribute_string = '';

					foreach ( $data_attributes as $name => $value ) {
						$data_attribute_string .= " data-${name}=\"" . esc_attr( $value ) . '"';
					}

					$data_attribute_string = trim( $data_attribute_string );

					$link = sprintf(
						"<a rel='nofollow' class='comment-reply-link' href='%s' %s aria-label='%s'>%s</a>",
						esc_url(
							add_query_arg(
								array(
									'replytocom'      => $comment->comment_ID,
									'unapproved'      => false,
									'moderation-hash' => false,
								),
								$args['original_permalink']
							)
						) . '#' . $args['respond_id'],
						$data_attribute_string,
						esc_attr( sprintf( $args['reply_to_text'], $comment->comment_author ) ),
						$args['reply_text']
					);
					return $args['before'] . $link . $args['after'];
				},
				10,
				4
			);

			comment_reply_link(
				array_merge(
					$args,
					array(
						'depth'     => $depth,
						'max_depth' => $args['max_depth'],
						'before'    => '<span class="alignright">',
						'after'     => '</span>',
					)
				)
			);
			?>
			</div><!-- .comment-author .vcard -->
			<?php if ( '0' === $comment->comment_approved ) : ?>
				<p><em><?php esc_html_e( 'Your comment is awaiting moderation.' ); ?></em></p>
			<?php endif; ?>
			<?php if ( $comment_translation_id && $comment_translation_id !== $current_translation_id ) : ?>
				<?php
					$translation           = GP::$translation->get( $comment_translation_id );
					$translation_permalink = GP_Route_Translation_Helpers::get_translation_permalink(
						$args['project'],
						$args['locale_slug'],
						$args['translation_set_slug'],
						$args['original_id'],
						$comment_translation_id
					);
				?>
				<em>
					<?php
					echo $is_a_rejection_feedback ? 'Translation (Rejected): ' : 'Translation: ';
					if ( $translation_permalink ) {
						echo wp_kses( gp_link( $translation_permalink, $translation->translation_0 ), array( 'a' => array( 'href' => true ) ) );
					} else {
						echo esc_html( $translation->translation_0 );
					}
					?>
				</em>
			<?php endif; ?>
			<div class="clear"></div>
			<div id="comment-reply-<?php echo esc_attr( $comment->comment_ID ); ?>" style="display: none;">
			<?php
			if ( is_user_logged_in() ) {
				comment_form(
					array(
						'title_reply'         => esc_html__( 'Discuss this string' ),
						/* translators: username */
						'title_reply_to'      => esc_html__( 'Reply to %s' ),
						'title_reply_before'  => '<h5 id="reply-title" class="discuss-title">',
						'title_reply_after'   => '</h5>',
						'id_form'             => 'commentform-' . $comment->comment_post_ID,
						'cancel_reply_link'   => '<span></span>',
						'comment_notes_after' => implode(
							"\n",
							array(
								'<input type="hidden" name="comment_parent" value="' . esc_attr( $comment->comment_ID ) . '" />',
								'<input type="hidden" name="comment_locale" value="' . esc_attr( $args['locale_slug'] ) . '" />',
								'<input type="hidden" name="translation_id" value="' . esc_attr( $args['translation_id'] ) . '" />',
								'<input type="hidden" name="redirect_to" value="' . esc_url( $args['original_permalink'] ) . '" />',
							)
						),
					),
					$comment->comment_post_ID
				);
			} else {
				/* translators: Log in URL. */
				echo sprintf( __( 'You have to be <a href="%s">logged in</a> to comment.' ), esc_html( wp_login_url() ) );
			}
			?>
			</div>
		</footer>
	</article><!-- #comment-## -->
</li>
	<?php
}
