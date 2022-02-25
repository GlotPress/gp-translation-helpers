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
		$this->template_path = dirname( __FILE__ ) . '/../templates/';
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
		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
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
		$translation_set      = GP::$translation_set->by_project_id_slug_and_locale( $project->id, $translation_set_slug, $locale_slug );
		$original             = GP::$original->get( $original_id );
		$all_translation_sets = GP::$translation_set->by_project_id( $project->id );

		if ( isset( $this->helpers['discussion'] ) ) {
			$translation_helper = $this->helpers['discussion'];
			$translation_helper->set_data( $args );

			$post_id               = $translation_helper::get_shadow_post( $original_id );
			$comments              = get_comments(
				array(
					'post_id'            => $post_id,
					'status'             => 'approve',
					'type'               => 'comment',
					'include_unapproved' => array( get_current_user_id() ),
				)
			);
			$locales_with_comments = $this->get_locales_with_comments( $comments );
		}
		$row_id      = $original_id;
		$translation = null;
		if ( $translation_id ) {
			$row_id     .= '-' . $translation_id;
			$translation = GP::$translation->get( $translation_id );
		}
		$original_permalink             = gp_url_project( $project, array( 'filters[original_id]' => $original_id ) );
		$original_translation_permalink = false;
		if ( $translation_set ) {
			$original_translation_permalink = gp_url_project_locale( $project, $locale_slug, $translation_set->slug, array( 'filters[original_id]' => $original_id ) );
		}

		wp_register_style( 'gp-discussion-css', plugins_url( '/../css/discussion.css', __FILE__ ), '', '0.0.1' ); // todo: add the version as global element.

		wp_register_script( 'gp-translation-discussion-js', plugins_url( '/../js/discussion.js', __FILE__ ), '', '0.0.1', true ); // todo: add the version as global element.

		add_filter(
			'comment_form_logged_in',
			function( $logged_in_as, $commenter, $user_identity ) {
				/* translators: Username with which the user is logged in */
				return sprintf( '<p class="logged-in-as">%s</p>', sprintf( __( 'Logged in as %s.' ), $user_identity ) );
			},
			10,
			3
		);

		add_filter(
			'comment_form_fields',
			function( $comment_fields ) {
				$comment_fields['comment'] = str_replace( '>Comment<', '>Please leave your comment about this string here:<', $comment_fields['comment'] );
				return $comment_fields;
			}
		);

		remove_action( 'comment_form_top', 'rosetta_comment_form_support_hint' );

		add_filter(
			'get_comment_author_link',
			function() {
				$comment_author = get_comment_author();
				return '<a href="https://profiles.wordpress.org/' . $comment_author . '">' . $comment_author . '</a>';
			}
		);

		/** Get translation for this original */
		$existing_translations = array();
		if ( ! $translation && $translation_set && $original_id ) {
			$existing_translations = GP::$translation->find_many_no_map(
				array(
					'status'             => 'current',
					'original_id'        => $original_id,
					'translation_set_id' => $translation_set->id,
				)
			);

			foreach ( $existing_translations as $e ) {
				if ( 'current' === $e->status ) {
					$translation = $e;
					break;
				}
			}

			if ( ! $translation ) {
				$existing_translations = GP::$translation->find_many_no_map(
					array(
						'original_id'        => $original_id,
						'translation_set_id' => $translation_set->id,
					)
				);
			}
		}

		$priorities_key_value = $original->get_static( 'priorities' );
		$priority             = $priorities_key_value[ $original->priority ];

		$sections = $this->get_translation_helper_sections( $project->id, $original_id, $locale_slug, $translation_set_slug, $translation_id, $translation );

		$translations       = GP::$translation->find_many_no_map(
			array(
				'status'      => 'current',
				'original_id' => $original_id,
			)
		);
		$no_of_translations = count( $translations );

		add_action(
			'gp_head',
			function() use ( $original, $no_of_translations ) {
				echo '<meta property="og:title" content="' . esc_html( $original->singular ) . ' | ' . esc_html( $no_of_translations ) . ' translations" />';
			}
		);

		$this->tmpl( 'original-permalink', get_defined_vars() );
	}

	/**
	 * Gets the sections of each active helper.
	 *
	 * @since 0.0.2
	 *
	 * @param int           $project_id             The project id. E.g. "11".
	 * @param int           $original_id            The original id. E.g. "2440".
	 * @param string|null   $locale_slug            Optional. The locale slug. E.g. "es".
	 * @param string|null   $translation_set_slug   The translation set slug. E.g. "default".
	 * @param int|null      $translation_id         Optional. The translation id. E.g. "4525".
	 * @param stdClass|null $translation            Optional. The translation object.
	 *
	 * @return array
	 */
	public function get_translation_helper_sections( $project_id, $original_id, $locale_slug = null, $translation_set_slug = null, $translation_id = null, $translation = null ) {
		$args     = compact( 'project_id', 'locale_slug', 'translation_set_slug', 'original_id', 'translation_id', 'translation' );
		$sections = array();
		foreach ( $this->helpers as $translation_helper ) {
			$translation_helper->set_data( $args );

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
			);
		}

		usort(
			$sections,
			function( $s1, $s2 ) {
				return $s1['priority'] > $s2['priority'];
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
	public function ajax_translation_helpers_locale( string $project_path, string $locale_slug, string $set_slug, int $original_id, int $translation_id = null ) {
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
	public function ajax_translation_helpers( string $project_path, int $original_id, int $translation_id = null, string $locale_slug = null, string $set_slug = null ): void {
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

		$single_helper = gp_get( 'helper' );
		$helpers       = $this->helpers;
		if ( isset( $this->helpers[ $single_helper ] ) ) {
			$helpers = array( $this->helpers[ $single_helper ] );
		}

		$sections = array();
		foreach ( $helpers as $translation_helper ) {
			$translation_helper->set_data( $args );
			if ( $translation_helper->has_async_content() && $translation_helper->activate() ) {
				$sections[ $translation_helper->get_div_id() ] = array(
					'content' => $translation_helper->get_async_output(),
					'count'   => $translation_helper->get_count(),
				);
			};
		}

		echo wp_json_encode( $sections );
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
	 * @param string      $project_path The project path. E.g. "wp/dev".
	 * @param int|null    $original_id  The original id. E.g. "2440".
	 * @param string|null $set_slug     The translation set slug. E.g. "default".
	 * @param string|null $locale_slug  Optional. The locale slug. E.g. "es".
	 *
	 * @return string                   The full permalink.
	 */
	public static function get_permalink( string $project_path, ?int $original_id, string $set_slug = null, string $locale_slug = null ): string {
		$permalink = $project_path . '/' . $original_id;
		if ( $set_slug && $locale_slug ) {
			$permalink .= '/' . $locale_slug . '/' . $set_slug;
		}
		return home_url( gp_url_project( $permalink ) );
	}

	public static function get_translation_permalink( $project, $locale_slug, $translation_set_slug, $original_id, $translation_id ) {
		if ( ! $project || ! $locale_slug || ! $translation_set_slug || ! $original_id || ! $translation_id ) {
			return false;
		}

		$translation_permalink = gp_url_project_locale(
			$project,
			$locale_slug,
			$translation_set_slug,
			array(
				'filters[status]'         => 'either',
				'filters[original_id]'    => $original_id,
				'filters[translation_id]' => $translation_id,
			)
		);
		return $translation_permalink;
	}
}
