<?php
/**
 * Routes: GP_Route_Translation_Helpers class
 *
 * @package gp-translation-helpers
 * @since 0.0.1
 */
class GP_Route_Translation_Helpers extends GP_Route {

	/**
	 * Stores an instance of each helper.
	 *
	 * @since 0.0.1
	 * @var array
	 */
	private $helpers = array();

	/**
	 * GP_Route_Translation_Helpers constructor.
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		$this->helpers       = GP_Translation_Helpers::load_helpers();
		$this->template_path = __DIR__ . '/../templates/';
	}

	/**
	 * Loads the 'discussions-dashboard' template.
	 *
	 * @since 0.0.2
	 *
	 * @param string|null $locale_slug          Optional. The locale slug. E.g. "es".
	 *
	 * @return void
	 */
	public function discussions_dashboard( $locale_slug ) {
		if ( ! is_user_logged_in() ) {
			$this->die_with_404();
		}
		$user_id = wp_get_current_user()->ID;

		$comments_per_page   = 12;
		$page_num_from_query = (int) get_query_var( 'page' );
		$page_number         = max( 1, $page_num_from_query );
		$offset              = ( $page_number - 1 ) * $comments_per_page;
		$filter              = isset( $_GET['filter'] ) ? esc_html( $_GET['filter'] ) : '';
		$gp_locale           = GP_Locales::by_slug( $locale_slug );

		$participating          = $this->get_user_comments( $locale_slug, $user_id );
		$participating_post_ids = array_values( array_unique( array_column( $participating, 'comment_post_ID' ) ) );

		$all_count               = $this->get_locale_post_count( $locale_slug );
		$participating_count     = count( $participating_post_ids );
		$not_participating_count = max( 0, $all_count - $participating_count );

		switch ( $filter ) {
			case 'participating':
				$page_post_ids = array_slice( $participating_post_ids, $offset, $comments_per_page );
				$total_pages   = (int) ceil( $participating_count / $comments_per_page );
				break;
			case 'not_participating':
				$page_post_ids = $this->get_paged_locale_post_ids( $locale_slug, $offset, $comments_per_page, $participating_post_ids );
				$total_pages   = (int) ceil( $not_participating_count / $comments_per_page );
				break;
			default:
				$page_post_ids = $this->get_paged_locale_post_ids( $locale_slug, $offset, $comments_per_page );
				$total_pages   = (int) ceil( $all_count / $comments_per_page );
		}

		$comments = array();
		if ( $page_post_ids ) {
			$comments_query = new WP_Comment_Query(
				array(
					'meta_key'   => 'locale',
					'meta_value' => $locale_slug,
					'post__in'   => $page_post_ids,
				)
			);
			$comments       = $comments_query->comments;
		}

		$this->tmpl( 'discussions-dashboard', get_defined_vars() );
	}

	/**
	 * Loads the 'original-permalink' template.
	 *
	 * @since 0.0.2
	 *
	 * @param string      $project_path         The project path. E.g. "wp/dev".
	 * @param int         $original_id          The original id. E.g. "2440".
	 * @param string|null $locale_slug          Optional. The locale slug. E.g. "es".
	 * @param string      $translation_set_slug The translation slug. E.g. "default".
	 * @param int|null    $translation_id       Optional. The translation id. E.g. "4525".
	 *
	 * @return void
	 */
	public function original_permalink( $project_path, $original_id, $locale_slug = null, $translation_set_slug = null, $translation_id = null ) {
		$original = GP::$original->get( $original_id );
		if ( ! $original ) {
			$this->die_with_404();
		}
		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			$this->die_with_404();
		}

		if ( $project->id !== $original->project_id ) {
			$project = GP::$project->get( $original->project_id );

			// Let's use the parameters that we have to create a URL in the right project.
			$corrected_url = self::get_permalink( $project->path, $original_id, $locale_slug, $translation_set_slug );

			wp_safe_redirect( $corrected_url );
			exit;
		}

		if ( ! $original ) {
			$this->die_with_404();
		}

		$args = array(
			'project_id'     => $project->id,
			'locale_slug'    => $locale_slug,
			'set_slug'       => $translation_set_slug,
			'original_id'    => $original_id,
			'translation_id' => $translation_id,
			'project'        => $project,

		);
		$translation_set = GP::$translation_set->by_project_id_slug_and_locale( $project->id, $translation_set_slug, $locale_slug );

		$all_translation_sets = GP::$translation_set->by_project_id( $project->id );

		$row_id      = $original_id;
		$translation = null;
		if ( $translation_id ) {
			$row_id     .= '-' . $translation_id;
			$translation = GP::$translation->get( $translation_id );
		}
		$original_permalink = gp_url_project( $project, array( 'filters[original_id]' => $original_id ) );

		$original_translation_permalink = false;
		if ( $translation_set ) {
			$original_translation_permalink = gp_url_project_locale( $project, $locale_slug, $translation_set->slug, array( 'filters[original_id]' => $original_id ) );
		}

		/** Get translation for this original */
		$existing_translations = array();
		if ( ! $translation && $translation_set && $original_id ) {
			$translation = GP::$translation->find_one(
				array(
					'status'             => 'current',
					'original_id'        => $original_id,
					'translation_set_id' => $translation_set->id,
				)
			);

			if ( ! $translation ) {
				$existing_translations = GP::$translation->find_many_no_map(
					array(
						'original_id'        => $original_id,
						'translation_set_id' => $translation_set->id,
					)
				);
				usort(
					$existing_translations,
					function ( $t1, $t2 ) {
						$cmp_prop_t1 = $t1->date_modified ?? $t1->date_added;
						$cmp_prop_t2 = $t2->date_modified ?? $t2->date_added;
						return $cmp_prop_t1 < $cmp_prop_t2;
					}
				);

				// Something falsy is not enough.
				$translation = null;
			}
		}

		$priorities_key_value = $original->get_static( 'priorities' );
		$priority             = $priorities_key_value[ $original->priority ];

		$args     = compact( 'project', 'locale_slug', 'translation_set_slug', 'original_id', 'translation_id', 'translation', 'original_permalink' );
		$sections = $this->get_translation_helper_sections( $args );

		$translations       = GP::$translation->find_many_no_map(
			array(
				'status'      => 'current',
				'original_id' => $original_id,
			)
		);
		$no_of_translations = count( $translations );

		add_action(
			'gp_head',
			function () use ( $original, $no_of_translations ) {
				echo '<meta property="og:title" content="' . esc_html( $original->singular ) . ' | ' . esc_html( $no_of_translations ) . ' translations" />';
			}
		);

		$this->tmpl( 'original-permalink', get_defined_vars() );
	}

	/**
	 * Gets the sections of each active helper.
	 *
	 * @param      array $data   The data to be passed on to the sections.
	 *
	 * @return     array   The translation helper sections.
	 */
	public function get_translation_helper_sections( $data ) {
		$sections = array();
		foreach ( $this->helpers as $helper => $translation_helper ) {
			$translation_helper->set_data( $data );

			if ( ! $translation_helper->activate() ) {
				continue;
			}

			$sections[] = array(
				'title'             => $translation_helper->get_title(),
				'content'           => $translation_helper->get_output(),
				'classname'         => $translation_helper->get_div_classname(),
				'id'                => $translation_helper->get_div_id(),
				'priority'          => $translation_helper->get_priority(),
				'has_async_content' => $translation_helper->has_async_content(),
				'count'             => $translation_helper->get_count(),
				'load_inline'       => $translation_helper->load_inline(),
				'helper'            => $helper,
			);
		}

		usort(
			$sections,
			function ( $s1, $s2 ) {
				return $s1['priority'] <=> $s2['priority'];
			}
		);

		return $sections;
	}

	/**
	 * Returns the content of each section (tab).
	 *
	 * @since 0.0.2
	 *
	 * @param string   $project_path    The project path. E.g. "wp/dev".
	 * @param string   $locale_slug     The locale slug. E.g. "es".
	 * @param string   $set_slug        The translation set slug. E.g. "default".
	 * @param int      $original_id     The original id. E.g. "2440".
	 * @param int|null $translation_id  Optional. The translation id. E.g. "4525".
	 *
	 * @return string                   JSON with the content of each section.
	 */
	public function ajax_translation_helpers_locale( string $project_path, string $locale_slug, string $set_slug, int $original_id, ?int $translation_id = null ) {
		return $this->ajax_translation_helpers( $project_path, $original_id, $translation_id, $locale_slug, $set_slug );
	}

	/**
	 * Returns the content of each section (tab).
	 *
	 * @since 0.0.1
	 *
	 * @param string      $project_path     The project path. E.g. "wp/dev".
	 * @param int         $original_id      The original id. E.g. "2440".
	 * @param int|null    $translation_id   Optional. The translation id. E.g. "4525".
	 * @param string|null $locale_slug      The locale slug. E.g. "es".
	 * @param string|null $set_slug         The translation set slug. E.g. "default".
	 *
	 * @return void                         Prints the JSON with the content of each section.
	 */
	public function ajax_translation_helpers( string $project_path, int $original_id, ?int $translation_id = null, ?string $locale_slug = null, ?string $set_slug = null ): void {
		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			$this->die_with_404();
		}

		$permalink = self::get_permalink( $project->path, $original_id, $set_slug, $locale_slug );

		$args = array(
			'project_id'           => $project->id,
			'locale_slug'          => $locale_slug,
			'translation_set_slug' => $set_slug,
			'original_id'          => $original_id,
			'translation_id'       => $translation_id,
			'permalink'            => $permalink,
			'project'              => $project,
		);

		$selected = gp_get( 'helpers' );
		if ( ! empty( $selected ) ) {
			$helpers = array_filter(
				$this->helpers,
				function ( $key ) use ( $selected ) {
					return in_array( $key, (array) $selected, true );
				},
				ARRAY_FILTER_USE_KEY
			);
		} else {
			$helpers = $this->helpers;
		}

		$sections = array();
		foreach ( $helpers as $translation_helper ) {
			$translation_helper->set_data( $args );
			if ( $translation_helper->has_async_content() && $translation_helper->activate() ) {
				$sections[ $translation_helper->get_div_id() ] = array(
					'content' => $translation_helper->get_async_output(),
					'count'   => $translation_helper->get_count(),
				);
			}
		}

		wp_send_json( $sections );
	}

	/**
	 * Gets the locales with comments.
	 *
	 * @since 0.0.2
	 *
	 * @param array|null $comments  Array with comments.
	 *
	 * @return array                Array with the locales with comments.
	 */
	private function get_locales_with_comments( ?array $comments ): array {
		$comment_locales = array();
		if ( $comments ) {
			foreach ( $comments as $comment ) {
				$comment_meta          = get_comment_meta( $comment->comment_ID, 'locale' );
				$single_comment_locale = is_array( $comment_meta ) && ! empty( $comment_meta ) ? $comment_meta[0] : '';
				if ( $single_comment_locale && ! in_array( $single_comment_locale, $comment_locales, true ) ) {
					$comment_locales[] = $single_comment_locale;
				}
			}
		}
		return $comment_locales;
	}

	/**
	 * Gets the full permalink.
	 *
	 * @since 0.0.2
	 *
	 * @param string          $project_path The project path. E.g. "wp/dev".
	 * @param string|int|null $original_id  The original id. E.g. "2440".
	 * @param string|null     $set_slug     The translation set slug. E.g. "default".
	 * @param string|null     $locale_slug  Optional. The locale slug. E.g. "es".
	 *
	 * @return string                       The full permalink.
	 *
	 * @todo Restore `string|int|null` type hint for $original_id when minimum PHP version is 8.0+.
	 */
	public static function get_permalink( string $project_path, $original_id, ?string $set_slug = null, ?string $locale_slug = null ): string {
		$permalink = $project_path . '/' . $original_id;
		if ( $set_slug && $locale_slug ) {
			$permalink .= '/' . $locale_slug . '/' . $set_slug;
		}
		return home_url( gp_url_project( $permalink ) );
	}

	/**
	 * Gets the translation permalink.
	 *
	 * @param      GP_Project $project               The project.
	 * @param      string     $locale_slug           The locale slug.
	 * @param      string     $translation_set_slug  The translation set slug.
	 * @param      int        $original_id           The original id.
	 * @param      int        $translation_id        The translation id.
	 *
	 * @return     bool    The translation permalink.
	 */
	public static function get_translation_permalink( $project, $locale_slug, $translation_set_slug, $original_id, $translation_id = null ) {
		if ( ! $project || ! $locale_slug || ! $translation_set_slug || ! $original_id ) {
			return false;
		}

		$args = array(
			'filters[original_id]' => $original_id,
		);

		if ( $translation_id ) {
			$args['filters[status]']         = 'either';
			$args['filters[translation_id]'] = $translation_id;
		}

		$translation_permalink = gp_url_project_locale(
			$project,
			$locale_slug,
			$translation_set_slug,
			$args
		);
		return $translation_permalink;
	}

	/**
	 * Gets distinct post_ids for all comments made by user
	 *
	 * @param      string $locale_slug           The locale slug.
	 * @param      int    $user_id           The user id.
	 *
	 * @return     array    The array of comment_post_IDs.
	 */
	private function get_user_comments( $locale_slug, $user_id ) {
		$args     = array(
			'meta_key'   => 'locale',
			'meta_value' => $locale_slug,
			'user_id'    => $user_id,
		);
		$query    = new WP_Comment_Query( $args );
		$comments = $query->comments;

		return $comments;
	}

	/**
	 * Fetches one page of comment_post_IDs for a locale, ordered by latest comment date.
	 *
	 * @param string $locale_slug      The locale slug.
	 * @param int    $offset           Page offset.
	 * @param int    $per_page         Page size.
	 * @param array  $exclude_post_ids Post IDs to exclude (used for the "not participating" filter).
	 *
	 * @return int[] Post IDs.
	 */
	private function get_paged_locale_post_ids( $locale_slug, $offset, $per_page, $exclude_post_ids = array() ) {
		global $wpdb;

		$exclude_sql = '';
		if ( $exclude_post_ids ) {
			$exclude_sql = ' AND c.comment_post_ID NOT IN (' . implode( ',', array_map( 'intval', $exclude_post_ids ) ) . ')';
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $exclude_sql is composed from intval'd integers.
		$sql = $wpdb->prepare(
			"SELECT c.comment_post_ID
			 FROM {$wpdb->commentmeta} cm
			 JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
			 WHERE cm.meta_key = %s AND cm.meta_value = %s
			 $exclude_sql
			 GROUP BY c.comment_post_ID
			 ORDER BY MAX(c.comment_date) DESC
			 LIMIT %d, %d",
			'locale',
			$locale_slug,
			$offset,
			$per_page
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is built via $wpdb->prepare() above.
		return array_map( 'intval', $wpdb->get_col( $sql ) );
	}

	/**
	 * Returns the number of posts that have at least one comment in the given locale.
	 *
	 * @param string $locale_slug The locale slug.
	 *
	 * @return int
	 */
	private function get_locale_post_count( $locale_slug ) {
		$cache_group  = 'wporg-translate-discussions';
		$last_changed = wp_cache_get_last_changed( 'comment' ) . ':' . wp_cache_get_last_changed( 'comment_meta' );
		$cache_key    = 'discussions_post_count:' . $locale_slug . ':' . $last_changed;

		$count = wp_cache_get( $cache_key, $cache_group );
		if ( false !== $count ) {
			return (int) $count;
		}

		global $wpdb;
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT c.comment_post_ID)
				 FROM {$wpdb->commentmeta} cm
				 JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
				 WHERE cm.meta_key = %s AND cm.meta_value = %s",
				'locale',
				$locale_slug
			)
		);

		wp_cache_set( $cache_key, $count, $cache_group, DAY_IN_SECONDS );
		return $count;
	}
}
