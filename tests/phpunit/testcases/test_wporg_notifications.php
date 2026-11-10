<?php
/**
 * Tests for the translate.wordpress.org notification messages and roles.
 *
 * The role lookups in WPorg_GlotPress_Notifications need a multisite with a
 * site per locale, so most tests here only run with WP_MULTISITE=1.
 */
class GP_Test_WPorg_Notifications extends GP_UnitTestCase {

	/**
	 * Locale site ids, keyed by WordPress locale.
	 *
	 * @var array
	 */
	private $locale_sites = array();

	/**
	 * The original used for the discussions.
	 *
	 * @var GP_Original
	 */
	private $original;

	/**
	 * The plugin project the PTEs are assigned to.
	 *
	 * @var GP_Project
	 */
	private $plugin_project;

	public function setUp(): void {
		parent::setUp();

		if ( ! defined( 'WPORG_TRANSLATE_BLOGID' ) ) {
			define( 'WPORG_TRANSLATE_BLOGID', get_current_blog_id() );
		}
		if ( ! defined( 'WPORG_GLOBAL_NETWORK_ID' ) ) {
			define( 'WPORG_GLOBAL_NETWORK_ID', get_current_network_id() );
		}
		WPorg_GlotPress_Notifications::init();

		foreach ( array( 'nl' => 'nl_NL', 'nl-be' => 'nl_BE', 'de' => 'de_DE' ) as $slug => $wp_locale ) {
			$this->factory->locale->create(
				array(
					'slug'      => $slug,
					'wp_locale' => $wp_locale,
				)
			);
		}

		$wp_plugins           = $this->factory->project->create( array( 'slug' => 'wp-plugins' ) );
		$this->plugin_project = $this->factory->project->create(
			array(
				'slug'              => 'test-plugin',
				'parent_project_id' => $wp_plugins->id,
			)
		);
		$stable               = $this->factory->project->create(
			array(
				'slug'              => 'stable',
				'parent_project_id' => $this->plugin_project->id,
			)
		);
		$this->original       = $this->factory->original->create( array( 'project_id' => $stable->id ) );

		global $wpdb;
		$wpdb->wporg_translation_editors = $wpdb->base_prefix . 'test_translation_editors';
		$wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->wporg_translation_editors} ( user_id bigint(20) unsigned NOT NULL, project_id bigint(20) unsigned NOT NULL, locale varchar(20) NOT NULL )" );
		// Plugin committers, read when checking whether the user is an author of the project.
		$wpdb->query( 'CREATE TABLE IF NOT EXISTS plugin_2_svn_access ( user varchar(60) NOT NULL, path varchar(255) NOT NULL )' );
	}

	/**
	 * Skips the test unless it runs on a multisite.
	 */
	private function require_multisite() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'The translate.wordpress.org roles need a site per locale (WP_MULTISITE=1).' );
		}

		// Stands in for the wporg site query locale support: a 'locale' query var
		// returns the site of that WordPress locale.
		add_filter(
			'sites_pre_query',
			function ( $sites, $query ) {
				if ( empty( $query->query_vars['locale'] ) ) {
					return $sites;
				}
				$site_id = $this->locale_sites[ $query->query_vars['locale'] ] ?? null;
				return $site_id ? array( $site_id ) : array();
			},
			10,
			2
		);
	}

	/**
	 * Creates a user who has opted in to notifications.
	 *
	 * @return WP_User The user.
	 */
	private function create_opted_in_user() {
		$user_id = $this->factory->user->create();
		update_user_option( $user_id, 'gp_default_sort', array( 'notifications_optin' => 'on' ), true );

		return get_user_by( 'id', $user_id );
	}

	/**
	 * Makes the user a GTE on the site of a locale.
	 *
	 * @param WP_User $user        The user.
	 * @param string  $locale_slug The GlotPress locale slug, e.g. 'nl-be'.
	 */
	private function make_gte( $user, $locale_slug ) {
		$wp_locale = GP_Locales::by_slug( $locale_slug )->wp_locale;
		if ( ! isset( $this->locale_sites[ $wp_locale ] ) ) {
			$this->locale_sites[ $wp_locale ] = self::factory()->blog->create();
		}
		$site_id = $this->locale_sites[ $wp_locale ];

		switch_to_blog( $site_id );
		add_role( 'general_translation_editor', 'General Translation Editor' );
		restore_current_blog();

		add_user_to_blog( $site_id, $user->ID, 'general_translation_editor' );
	}

	/**
	 * Makes the user a PTE (or a CLPTE with 'all-locales') for the plugin project.
	 *
	 * @param WP_User $user        The user.
	 * @param string  $locale_slug The GlotPress locale slug, or 'all-locales'.
	 */
	private function make_pte( $user, $locale_slug ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->wporg_translation_editors,
			array(
				'user_id'    => $user->ID,
				'project_id' => $this->plugin_project->id,
				'locale'     => $locale_slug,
			)
		);
	}

	/**
	 * Gets the opt-in message the user sees on the discussion of the original.
	 *
	 * @param WP_User     $user        The user.
	 * @param string|null $locale_slug The locale of the discussion page, null if none.
	 *
	 * @return string The message.
	 */
	private function optin_message( $user, $locale_slug ) {
		wp_set_current_user( $user->ID );

		return GP_Notifications::optin_message_for_each_discussion( $this->original->id, $locale_slug );
	}

	public function test_optin_message_passes_the_locale_to_the_filter() {
		$received = null;
		add_filter(
			'gp_get_optin_message_for_each_discussion',
			function ( $message, $original_id, $locale_slug = null ) use ( &$received ) {
				$received = $locale_slug;
				return 'filtered';
			},
			20,
			3
		);

		$message = GP_Notifications::optin_message_for_each_discussion( $this->original->id, 'nl' );

		$this->assertSame( 'filtered', $message );
		$this->assertSame( 'nl', $received );
	}

	public function test_gte_message_shows_on_a_discussion_in_the_gte_locale() {
		$this->require_multisite();
		$user = $this->create_opted_in_user();
		$this->make_gte( $user, 'nl-be' );

		$this->assertStringContainsString( 'because you are a GTE', $this->optin_message( $user, 'nl-be' ) );
	}

	public function test_gte_message_does_not_show_on_a_discussion_in_another_locale() {
		$this->require_multisite();
		$user = $this->create_opted_in_user();
		$this->make_gte( $user, 'nl-be' );

		$message = $this->optin_message( $user, 'nl' );

		$this->assertStringNotContainsString( 'because you are a GTE', $message );
		$this->assertStringContainsString( 'You will not receive notifications for this discussion.', $message );
	}

	public function test_gte_message_shows_on_a_discussion_without_a_locale() {
		$this->require_multisite();
		$user = $this->create_opted_in_user();
		$this->make_gte( $user, 'nl-be' );

		$this->assertStringContainsString( 'because you are a GTE', $this->optin_message( $user, null ) );
	}

	public function test_pte_message_shows_on_a_discussion_in_the_pte_locale() {
		$this->require_multisite();
		$user = $this->create_opted_in_user();
		$this->make_pte( $user, 'nl' );

		$this->assertStringContainsString( 'because you are a PTE', $this->optin_message( $user, 'nl' ) );
	}

	public function test_pte_message_does_not_show_on_a_discussion_in_another_locale() {
		$this->require_multisite();
		$user = $this->create_opted_in_user();
		$this->make_pte( $user, 'nl' );

		$message = $this->optin_message( $user, 'de' );

		$this->assertStringNotContainsString( 'because you are a PTE', $message );
		$this->assertStringContainsString( 'You will not receive notifications for this discussion.', $message );
	}

	public function test_pte_message_shows_on_a_discussion_without_a_locale() {
		$this->require_multisite();
		$user = $this->create_opted_in_user();
		$this->make_pte( $user, 'nl' );

		$this->assertStringContainsString( 'because you are a PTE', $this->optin_message( $user, null ) );
	}

	public function test_gte_of_one_locale_and_pte_of_another_gets_the_pte_message() {
		$this->require_multisite();
		$user = $this->create_opted_in_user();
		$this->make_gte( $user, 'nl-be' );
		$this->make_pte( $user, 'nl' );

		$message = $this->optin_message( $user, 'nl' );

		$this->assertStringContainsString( 'because you are a PTE', $message );
		$this->assertStringNotContainsString( 'because you are a GTE', $message );
	}

	public function test_validator_details_name_each_role() {
		$this->require_multisite();
		$gte   = $this->create_opted_in_user();
		$pte   = $this->create_opted_in_user();
		$clpte = $this->create_opted_in_user();
		$this->make_gte( $gte, 'nl' );
		$this->make_pte( $pte, 'nl' );
		$this->make_pte( $clpte, 'all-locales' );

		$roles = array_column( WPorg_GlotPress_Notifications::get_validator_details_for_original_id( 'nl', $this->original->id ), 'role', 'email' );

		$this->assertSame(
			array(
				$gte->user_email   => 'GTE',
				$pte->user_email   => 'PTE',
				$clpte->user_email => 'CLPTE',
			),
			$roles
		);
	}
}
