<?php

class GP_Translation_Helpers {
	public $id       = 'translation-helpers';
	private $helpers = array();

	private static $instance = null;

	public static function init() {
		self::get_instance();
	}

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'register_routes' ), 5 );
		add_action( 'gp_before_request', array( $this, 'before_request' ), 10, 2 );

		add_filter(
			'gp_translation_row_template_more_links',
			function( $more_links, $project, $locale, $translation_set, $translation ) {
				$route_translation_helpers = new GP_Route_Translation_Helpers();
				$permalink                 = $route_translation_helpers->get_permalink( $project->path, $translation->original_id, $translation_set->slug, $translation_set->locale );

				$translation_helper = $this->helpers['discussion'];
				$comments           = $route_translation_helpers->get_translation_comments( $translation_helper, $translation->original_id );
				$num_of_comments     = count( $comments );

				$more_links['discussion'] = '<a href="' . esc_url( $permalink ) . '">Discussion (' . $num_of_comments . ')</a>';

				return $more_links;

			},
			10,
			5
		);

		// Prevent remote POST to comment forms
		add_filter(
			'preprocess_comment',
			function( $commentdata ) {
				if ( ! $commentdata['user_ID'] ) {
					die( 'User not authorized!' );
				}
				return $commentdata;
			}
		);

		$this->helpers = self::load_helpers();
	}

	public function before_request( $class_name, $last_method ) {
		if (
			in_array(
				$class_name . '::' . $last_method,
				array(
					// 'GP_Route_Translation::translations_get',
					'GP_Route_Translation_Helpers::original_permalink',
				)
			)
		) {
			add_action( 'gp_pre_tmpl_load', array( $this, 'pre_tmpl_load' ), 10, 2 );
		}
	}

	public function pre_tmpl_load( $template, $args ) {
			$allowed_templates = apply_filters( 'gp_translations_helpers_templates', array( 'original-permalink' ) );

		if ( ! in_array( $template, $allowed_templates, true ) ) {
			return;
		}

		$translation_helpers_settings = array(
			'th_url' => gp_url_project( $args['project'], gp_url_join( $args['locale_slug'], $args['translation_set_slug'], '-get-translation-helpers' ) ),
		);

		add_action( 'gp_head', array( $this, 'css_and_js' ), 10 );
		add_action( 'gp_translation_row_editor_columns', array( $this, 'translation_helpers' ), 10, 2 );

		add_filter(
			'gp_translation_row_editor_clospan',
			function( $colspan ) {
				return ( $colspan - 3 );
			}
		);

		wp_register_style( 'gp-translation-helpers-css', plugins_url( 'css/translation-helpers.css', __DIR__ ) );
		gp_enqueue_style( 'gp-translation-helpers-css' );

		wp_register_script( 'gp-translation-helpers', plugins_url( '/js/translation-helpers.js', __DIR__ ), array( 'gp-editor' ), '2017-02-09' );
		gp_enqueue_scripts( array( 'gp-translation-helpers' ) );

		wp_localize_script( 'gp-translation-helpers', '$gp_translation_helpers_settings', $translation_helpers_settings );
		wp_localize_script(
			'gp-translation-helpers',
			'wpApiSettings',
			array(
				'root'  => esc_url_raw( rest_url() ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
	public static function load_helpers() {
		$base_dir = dirname( dirname( __FILE__ ) ) . '/helpers/';
		require_once $base_dir . '/base-helper.php';

		$helpers_files = array(
			'helper-translation-discussion.php',
			'helper-other-locales.php',
			'helper-translation-history.php',
			// 'helper-translation-memory.php',
			'helper-user-info.php',
		);

		foreach ( $helpers_files as $helper ) {
			require_once $base_dir . $helper;
		}

		$helpers = array();

		$classes = get_declared_classes();
		foreach ( $classes as $declared_class ) {
			$reflect = new ReflectionClass( $declared_class );
			if ( $reflect->isSubclassOf( 'GP_Translation_Helper' ) ) {
				$helpers[ sanitize_title_with_dashes( $reflect->getDefaultProperties()['title'] ) ] = new $declared_class();
			}
		}

		return $helpers;
	}

	public function translation_helpers( $t, $translation_set ) {
		$args = array(
			'project_id'           => $t->project_id,
			'locale_slug'          => $translation_set->locale,
			'translation_set_slug' => $translation_set->slug,
			'original_id'          => $t->original_id,
			'translation_id'       => $t->id,
			'translation'          => $t,
		);

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

		gp_tmpl_load( 'translation-helpers', array( 'sections' => $sections ), dirname( __FILE__ ) . '/templates/' );
	}


	public function register_routes() {
		$dir      = '([^_/][^/]*)';
		$path     = '(.+?)';
		$projects = 'projects';
		$project  = $projects . '/' . $path;
		$locale   = '(' . implode( '|', wp_list_pluck( GP_Locales::locales(), 'slug' ) ) . ')';
		$set      = "$project/$locale/$dir";
		$id       = '(\d+)-?(\d+)?';

		GP::$router->prepend( "/$project/(\d+)(?:/$locale/$dir)?(/\d+)?", array( 'GP_Route_Translation_Helpers', 'original_permalink' ), 'get' );
		GP::$router->prepend( "/$project/-get-translation-helpers/$id", array( 'GP_Route_Translation_Helpers', 'ajax_translation_helpers' ), 'get' );
		GP::$router->prepend( "/$project/$locale/$dir/-get-translation-helpers/$id", array( 'GP_Route_Translation_Helpers', 'ajax_translation_helpers_locale' ), 'get' );
	}

	public function css_and_js() {
		?>
		<style>
			<?php
			foreach ( $this->helpers as $translation_helper ) {
				$css = $translation_helper->get_css();
				if ( $css ) {
					echo '/* Translation Helper:  ' . esc_js( $translation_helper->get_title() ) . ' */' . "\n";
					echo $css . "\n";
				}
			}
			?>
		</style>
		<script>
			<?php
			foreach ( $this->helpers as $translation_helper ) {
				$js = $translation_helper->get_js();
				if ( $js ) {
					echo '/* Translation Helper:  ' . esc_js( $translation_helper->get_title() ) . ' */' . "\n";
					echo $js . "\n";
				}
			}
			?>
		</script>
		<?php
	}

}
