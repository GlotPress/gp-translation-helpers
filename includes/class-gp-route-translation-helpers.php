<?php

class GP_Route_Translation_Helpers extends GP_Route {

	private $helpers = array();

	public function __construct() {
		$this->helpers       = GP_Translation_Helpers::load_helpers();
		$this->template_path = dirname( __FILE__ ) . '/../templates/';
	}


	public function original_permalink( $project_path, $original_id, $locale_slug = null, $translation_set_slug = null, $translation_id = null ) {
		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			$this->die_with_404();
		}

		$args                 = array(
			'project_id'     => $project->id,
			'locale_slug'    => $locale_slug,
			'set_slug'       => $translation_set_slug,
			'original_id'    => $original_id,
			'translation_id' => $translation_id,
		);
		$translation_set      = GP::$translation_set->by_project_id_slug_and_locale( $project->id, $translation_set_slug, $locale_slug );
		$original             = GP::$original->get( $original_id );
		$all_translation_sets = GP::$translation_set->by_project_id( $project->id );

		if ( isset( $this->helpers['discussion'] ) ) {
			$translation_helper = $this->helpers['discussion'];
			$translation_helper->set_data( $args );

			$comments              = $this->get_translation_comments( $translation_helper, $original_id );
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

		wp_register_style( 'gp-discussion-css', plugins_url( '/../css/discussion.css', __FILE__ ) );

		wp_register_script( 'gp-translation-discussion-js', plugins_url( '/../js/discussion.js', __FILE__ ) );

		add_filter(
			'comment_form_logged_in',
			function( $logged_in_as, $commenter, $user_identity ) {
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
				echo '<meta property="og:title" content="' . esc_html( $original->singular ) . ' | ' . $no_of_translations . ' translations" />';
			}
		);

		$this->tmpl( 'original-permalink', get_defined_vars() );
	}


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

	public function ajax_translation_helpers_locale( $project_path, $locale_slug, $set_slug, $original_id, $translation_id = null ) {
		return $this->ajax_translation_helpers( $project_path, $original_id, $translation_id, $locale_slug, $set_slug );
	}

	public function ajax_translation_helpers( $project_path, $original_id, $translation_id = null, $locale_slug = null, $set_slug = null ) {
		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			$this->die_with_404();
		}

		$permalink = $this->get_permalink( $project->path, $original_id, $set_slug, $locale_slug );

		$args = array(
			'project_id'           => $project->id,
			'locale_slug'          => $locale_slug,
			'translation_set_slug' => $set_slug,
			'original_id'          => $original_id,
			'translation_id'       => $translation_id,
			'permalink'            => $permalink,
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

	private function get_locales_with_comments( $comments ) {
		$comment_locales = array();
		if ( $comments ) {
			foreach ( $comments as $comment ) {
				$comment_meta          = get_comment_meta( $comment->comment_ID, 'locale' );
				$single_comment_locale = is_array( $comment_meta ) && ! empty( $comment_meta ) ? $comment_meta[0] : '';
				if ( $single_comment_locale && ! in_array( $single_comment_locale, $comment_locales ) ) {
					$comment_locales[] = $single_comment_locale;
				}
			}
		}
		return $comment_locales;
	}

	public function get_permalink( $project_path, $original_id, $set_slug = null, $locale_slug = null ) {
		$permalink = '/projects/' . $project_path . '/' . $original_id;
		if ( $set_slug && $locale_slug ) {
			$permalink .= '/' . $locale_slug . '/' . $set_slug;
		}
		$permalink = home_url( $permalink );
		return $permalink;
	}

	public function get_translation_comments( $translation_helper, $original_id ) {
		$post_id  = $translation_helper::get_shadow_post( $original_id );
		$comments = get_comments(
			array(
				'post_id'            => $post_id,
				'status'             => 'approve',
				'type'               => 'comment',
				'include_unapproved' => array( get_current_user_id() ),
			)
		);
		return $comments;
	}
}
