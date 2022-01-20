<?php

class Helper_Translation_Discussion extends GP_Translation_Helper {

	public $priority          = 0;
	public $title             = 'Discussion';
	public $has_async_content = true;

	const POST_TYPE          = 'gth_original';
	const POST_STATUS        = 'publish';
	const LINK_TAXONOMY      = 'gp_original_id';
	const URL_SLUG           = 'discuss';
	const ORIGINAL_ID_PREFIX = 'original-';

	function after_constructor() {
		$this->register_post_type_and_taxonomy();
		add_filter( 'pre_comment_approved', array( $this, 'comment_moderation' ), 10, 2 );
	}

	public function register_post_type_and_taxonomy() {
		register_taxonomy(
			self::LINK_TAXONOMY,
			array(),
			array(
				'public'  => false,
				'show_ui' => false,
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
			)
		);
	}

	public function comment_moderation( $approved, $commentdata ) {
		global $wpdb;

		// If the comment is already approved, we're good.
		if ( $approved ) {
			return $approved;
		}

		// We only care on comments on our specific post type
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

	public static function get_original_from_post_id( $post_id ) {
		$terms = wp_get_object_terms( $post_id, self::LINK_TAXONOMY, array( 'number' => 1 ) );
		if ( empty( $terms ) ) {
			return false;
		}

		return $terms[0]->slug;
	}

	public static function get_shadow_post( $original_id ) {
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
							self::LINK_TAXONOMY => array( $original_id ),
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

	public function get_async_content() {
		return get_comments(
			array(
				'post_id'            => self::get_shadow_post( $this->data['original_id'] ),
				'status'             => 'approve',
				'type'               => 'comment',
				'include_unapproved' => array( get_current_user_id() ),
			)
		);
	}

	public function async_output_callback( $comments ) {
		// Remove comment likes for now (or forever :) ).
		remove_filter( 'comment_text', 'comment_like_button', 12 );

		// Disable subscribe to posts.
		add_filter( 'option_stb_enabled', '__return_false' );

		// Disable subscribe to comments for now.
		add_filter( 'option_stc_disabled', '__return_true' );

		// Link comment author to WordPress.org profile
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
				'comments'           => $comments,
				'post_id'            => self::get_shadow_post( $this->data['original_id'] ),
				'translation_id'     => isset( $this->data['translation_id'] ) ? $this->data['translation_id'] : null,
				'locale_slug'        => $this->data['locale_slug'],
				'original_permalink' => $this->data['permalink'],
				'languages'          => $this->generate_comment_filter_links( $comments, $this->data['locale_slug'] ),
			),
			$this->assets_dir . 'templates'
		);
		return $output;
	}

	public function generate_comment_filter_links( $comments, $locale_slug ) {
		if ( ! $comments ) {
			return array();
		}
		// Go through all $comments and map their values to the callback of this function.
		$comment_locales = array_map(
			function( $comment ) {
				return get_comment_meta( $comment->comment_ID, 'locale', true );
			},
			$comments
		);
		// Produce an array of the form $locale => count( entries of array with $locale ).
		$comment_count = array_count_values( $comment_locales );
		// Extract the locale count and ensure that it was set.
		$locale_count  = isset( $comment_count[ $locale_slug ] ) ? $comment_count[ $locale_slug ] : 0;
		$other_count   = array_sum( $comment_count ) - $locale_count;
		$gp_locale     = GP_Locales::by_slug( $locale_slug );
		$locale_name   = $gp_locale ? $gp_locale->english_name : 'Current locale';
		$comment_links = array(
			$locale_slug => $locale_name . ' (' . $locale_count . ')',
		);
		if ( $other_count ) {
			$comment_links['all'] = 'Other locales (+' . $other_count . ')';
		}
		return $comment_links;
	}

	public function empty_content() {
		return $this->async_output_callback( array() );
	}

	public function get_css() {
		return file_get_contents( $this->assets_dir . 'css/translation-discussion.css' );
	}
	public function get_js() {
		return file_get_contents( $this->assets_dir . 'js/translation-discussion.js' );
	}

	public function sanitize_comment_topic( $comment_topic ) {
		if ( ! in_array( $comment_topic, array( 'typo', 'context', 'question' ) ) ) {
			$comment_topic = 'unknown';
		}
		return $comment_topic;

	}

	public function sanitize_comment_locale( $comment_locale ) {
		$gp_locales     = new GP_Locales();
		$all_gp_locales = array_keys( $gp_locales->locales );

		if ( ! in_array( $comment_locale, $all_gp_locales ) ) {
			$comment_locale = '';
		}
		return $comment_locale;
	}

	public function sanitize_translation_id( $translation_id ) {
		if ( ! is_numeric( $translation_id ) ) {
			if ( $translation_id > 0 && ! GP::$translation->get( $translation_id ) ) {
				wp_die( 'Invalid translation ID' );
			}
		}
		return $translation_id;
	}
}

function gth_discussion_get_original_id_from_post( $post_id ) {
	return Helper_Translation_Discussion::get_original_from_post_id( $post_id );
}

function gth_discussion_callback( $comment, $args, $depth ) {
	$GLOBALS['comment'] = $comment;

	$comment_locale = get_comment_meta( $comment->comment_ID, 'locale', true );
	$current_locale = $args['locale_slug'];

	$current_translation_id = $args['translation_id'];
	$comment_translation_id = get_comment_meta( $comment->comment_ID, 'translation_id', true );
	?>
<li class="<?php echo esc_attr( 'comment-locale-' . $comment_locale ); ?>">
	<article id="comment-<?php comment_ID(); ?>" class="comment">
		<div class="comment-avatar">
			<?php echo get_avatar( $comment, 25 ); ?>
		</div><!-- .comment-avatar -->
		<?php printf( '<cite class="fn">%s</cite>', get_comment_author_link( $comment->comment_ID ) ); ?>
		<?php
		// Older than a week, show date; otherwise show __ time ago.
		if ( current_time( 'timestamp' ) - get_comment_time( 'U' ) > 604800 ) {
			$time = sprintf( _x( '%1$s at %2$s', '1: date, 2: time' ), get_comment_date(), get_comment_time() );
		} else {
			$time = sprintf( __( '%1$s ago' ), human_time_diff( get_comment_time( 'U' ), current_time( 'timestamp' ) ) );
		}
		echo '<time datetime=" ' . get_comment_time( 'c' ) . '">' . $time . '</time>';
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
		<div class="comment-content" dir="auto"><?php comment_text(); ?></div>
		<footer>
			<div class="comment-author vcard">
				<?php
				if ( $comment->comment_parent ) {
					printf(
						'<a href="%1$s">%2$s</a>',
						esc_url( get_comment_link( $comment->comment_parent ) ),
						sprintf( __( 'in reply to %s' ), get_comment_author( $comment->comment_parent ) )
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
			<?php if ( $comment->comment_approved == '0' ) : ?>
				<em><?php _e( 'Your comment is awaiting moderation.' ); ?></em>
			<?php endif; ?>
			<?php if ( $comment_translation_id && $comment_translation_id !== $current_translation_id ) : ?>
				<?php $translation = GP::$translation->get( $comment_translation_id ); ?>
				<em>Translation: <?php echo esc_translation( $translation->translation_0 ); ?></em>
			<?php endif; ?>
		</footer>
	</article><!-- #comment-## -->
</li>
	<?php
}
