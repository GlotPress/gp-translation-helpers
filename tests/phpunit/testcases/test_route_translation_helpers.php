<?php

class GP_Test_Route_Translation_Helpers extends GP_UnitTestCase_Route {
	public $route_class = 'GP_Route_Translation_Helpers';

	private function create_original( $set, $priority = 0, $singular = 'Original string' ) {
		return $this->factory->original->create(
			array(
				'project_id' => $set->project->id,
				'status'     => '+active',
				'singular'   => $singular,
				'priority'   => $priority,
			)
		);
	}

	/**
	 * Creates a discussion comment on an original's shadow post.
	 *
	 * @param GP_Original $original    The original to discuss.
	 * @param string      $locale_slug The locale the comment is filed under.
	 * @param int         $user_id     The comment author.
	 * @param string      $content     The comment content.
	 * @param array       $args        Overrides for wp_insert_comment(), e.g. comment_approved.
	 *
	 * @return int|false The comment id.
	 */
	private function create_discussion( $original, $locale_slug, $user_id, $content = 'A discussion comment', $args = array() ) {
		$post_id = Helper_Translation_Discussion::get_or_create_shadow_post_id( $original->id );
		$user    = get_user_by( 'id', $user_id );

		return wp_insert_comment(
			array_merge(
				array(
					'comment_post_ID'      => $post_id,
					'comment_content'      => $content,
					'comment_author'       => $user->display_name,
					'comment_author_email' => $user->user_email,
					'user_id'              => $user_id,
					'comment_meta'         => array(
						'reject_reason'  => array(),
						'translation_id' => 0,
						'locale'         => $locale_slug,
					),
				),
				$args
			)
		);
	}

	private function request_route( $method, ...$args ) {
		try {
			$this->route->$method( ...$args );
		} catch ( Exception $e ) {
			// A halted request (die_with_404()) throws when the loaded GlotPress
			// makes exit_() halt a faked request.
		}
	}

	/**
	 * Renders the discussions dashboard for a locale.
	 *
	 * @param string     $locale_slug The locale slug.
	 * @param string     $filter      The participation filter, if any.
	 * @param int|string $page        The requested page, if any.
	 *
	 * @return string The rendered dashboard.
	 */
	private function dashboard_output( $locale_slug, $filter = '', $page = '' ) {
		$_GET['filter']         = $filter;
		$_GET['page']           = $page;
		$_SERVER['REQUEST_URI'] = add_query_arg( array_filter( compact( 'page', 'filter' ) ), '/locale/' . $locale_slug . '/default/discussions/' );

		$this->request_route( 'discussions_dashboard', $locale_slug );

		return (string) $this->route->template_output;
	}

	function test_original_permalink_hides_a_hidden_original_from_anonymous_users() {
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$hidden = $this->create_original( $set, -2, 'Hidden string' );

		$this->request_route( 'original_permalink', $set->project->path, $hidden->id, $set->locale, $set->slug );

		$this->assert404();
	}

	function test_original_permalink_serves_a_normal_original_to_anonymous_users() {
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$normal = $this->create_original( $set, 0, 'Public string' );

		$this->request_route( 'original_permalink', $set->project->path, $normal->id, $set->locale, $set->slug );

		$this->assertEquals( 'original-permalink', $this->route->loaded_template );
	}

	function test_original_permalink_shows_a_hidden_original_to_a_user_who_can_write() {
		$this->set_admin_user_as_current();

		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$hidden = $this->create_original( $set, -2, 'Hidden string' );

		$this->request_route( 'original_permalink', $set->project->path, $hidden->id, $set->locale, $set->slug );

		$this->assertEquals( 'original-permalink', $this->route->loaded_template );
	}

	function test_original_permalink_does_not_disclose_a_hidden_original_through_another_project() {
		$set_a  = $this->factory->translation_set->create_with_project_and_locale();
		$hidden = $this->create_original( $set_a, -2, 'Hidden string' );
		$set_b  = $this->factory->translation_set->create_with_project_and_locale();

		$this->request_route( 'original_permalink', $set_b->project->path, $hidden->id, $set_b->locale, $set_b->slug );

		$this->assert404();
	}

	function test_ajax_translation_helpers_hides_a_hidden_original_from_anonymous_users() {
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$hidden = $this->create_original( $set, -2, 'Hidden string' );

		$this->request_route( 'ajax_translation_helpers', $set->project->path, $hidden->id, null, $set->locale, $set->slug );

		$this->assert404();
	}

	function test_ajax_translation_helpers_locale_hides_a_hidden_original_from_anonymous_users() {
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$hidden = $this->create_original( $set, -2, 'Hidden string' );

		$this->request_route( 'ajax_translation_helpers_locale', $set->project->path, $set->locale, $set->slug, $hidden->id );

		$this->assert404();
	}

	function test_discussions_dashboard_is_not_available_to_anonymous_users() {
		$set = $this->factory->translation_set->create_with_project_and_locale();

		$this->request_route( 'discussions_dashboard', $set->locale );

		$this->assert404();
	}

	function test_discussions_dashboard_lists_discussions_for_a_logged_in_user() {
		$author = $this->set_admin_user_as_current();
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$first  = $this->create_original( $set, 0, 'First string' );
		$second = $this->create_original( $set, 0, 'Second string' );
		$this->create_discussion( $first, $set->locale, $author );
		$this->create_discussion( $second, $set->locale, $author );

		$this->set_normal_user_as_current();
		$output = $this->dashboard_output( $set->locale );

		$this->assertEquals( 'discussions-dashboard', $this->route->loaded_template );
		$this->assertStringContainsString( 'First string', $output );
		$this->assertStringContainsString( 'Second string', $output );
		$this->assertStringContainsString( 'All&nbsp;(2)', $output );
	}

	function test_discussions_dashboard_keeps_its_toolbar_on_a_page_past_the_end() {
		$author = $this->set_admin_user_as_current();
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$first  = $this->create_original( $set, 0, 'First string' );
		$second = $this->create_original( $set, 0, 'Second string' );
		$this->create_discussion( $first, $set->locale, $author );
		$this->create_discussion( $second, $set->locale, $author );

		$output = $this->dashboard_output( $set->locale, '', '99999999999999999999' );

		$this->assertStringNotContainsString( 'First string', $output );
		$this->assertStringNotContainsString( 'Second string', $output );
		$this->assertStringContainsString( 'There are no discussions on this page', $output );
		$this->assertStringContainsString( 'All&nbsp;(2)', $output );
	}

	/**
	 * Regression for #245: the page came from a query var translate.wordpress.org drops.
	 */
	function test_discussions_dashboard_pages_through_discussions() {
		$author = $this->set_admin_user_as_current();
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$this->create_discussion( $this->create_original( $set, 0, 'Oldest string' ), $set->locale, $author, 'A discussion comment', array( 'comment_date' => '2026-01-01 00:00:00' ) );
		for ( $i = 1; $i <= 12; $i++ ) {
			$this->create_discussion( $this->create_original( $set, 0, 'Newer string ' . $i ), $set->locale, $author, 'A discussion comment', array( 'comment_date' => sprintf( '2026-01-02 00:00:%02d', $i ) ) );
		}

		$this->set_normal_user_as_current();
		$output = $this->dashboard_output( $set->locale, '', 2 );

		$this->assertStringContainsString( 'Oldest string', $output );
		$this->assertStringNotContainsString( 'Newer string', $output );
		$this->assertMatchesRegularExpression( '#<span[^>]*>2</span>#', $output );
		$this->assertStringContainsString( 'href="/locale/' . $set->locale . '/default/discussions/?filter=participating"', $output );
	}

	function test_discussions_dashboard_says_so_when_a_locale_has_no_discussions() {
		$set = $this->factory->translation_set->create_with_project_and_locale();

		$this->set_normal_user_as_current();
		$output = $this->dashboard_output( $set->locale );

		$this->assertStringContainsString( 'There are no discussions in this locale', $output );
	}

	function test_discussions_dashboard_hides_a_discussion_on_a_hidden_original_from_a_user_who_cannot_write() {
		$author = $this->set_admin_user_as_current();
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$hidden = $this->create_original( $set, -2, 'Hidden string' );
		$public = $this->create_original( $set, 0, 'Public string' );
		$this->create_discussion( $hidden, $set->locale, $author );
		$this->create_discussion( $public, $set->locale, $author );

		$this->set_normal_user_as_current();
		$output = $this->dashboard_output( $set->locale );

		$this->assertStringContainsString( 'Public string', $output );
		$this->assertStringNotContainsString( 'Hidden string', $output );
	}

	function test_discussions_dashboard_shows_a_discussion_on_a_hidden_original_to_a_user_who_can_write() {
		$author = $this->set_admin_user_as_current();
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$hidden = $this->create_original( $set, -2, 'Hidden string' );
		$public = $this->create_original( $set, 0, 'Public string' );
		$this->create_discussion( $hidden, $set->locale, $author );
		$this->create_discussion( $public, $set->locale, $author );

		$output = $this->dashboard_output( $set->locale );

		$this->assertStringContainsString( 'Public string', $output );
		$this->assertStringContainsString( 'Hidden string', $output );
		$this->assertStringContainsString( 'All&nbsp;(2)', $output );
	}

	function test_discussions_dashboard_hides_a_hidden_original_from_the_participation_filters() {
		$admin  = $this->set_admin_user_as_current();
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$hidden = $this->create_original( $set, -2, 'Hidden string' );
		$public = $this->create_original( $set, 0, 'Public string' );
		$this->create_discussion( $public, $set->locale, $admin );

		// The user took part in the hidden discussion but cannot write to the project.
		$user = $this->set_normal_user_as_current();
		$this->create_discussion( $hidden, $set->locale, $user );

		$participating = $this->dashboard_output( $set->locale, 'participating' );
		$this->assertStringNotContainsString( 'Hidden string', $participating );
		$this->assertStringContainsString( 'There are no discussions on this page', $participating );
		$this->assertStringContainsString( 'Not participating&nbsp;(1)', $participating, 'The filter toolbar is rendered on an empty page.' );

		$not_participating = $this->dashboard_output( $set->locale, 'not_participating' );
		$this->assertStringContainsString( 'Public string', $not_participating );
		$this->assertStringNotContainsString( 'Hidden string', $not_participating );
		$this->assertStringContainsString( 'Not participating&nbsp;(1)', $not_participating );
	}

	function test_discussions_dashboard_re_adds_the_first_comment_of_a_bulk_thread() {
		$author = $this->set_admin_user_as_current();
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$first  = $this->create_original( $set, 0, 'First string' );
		$second = $this->create_original( $set, 0, 'Second string' );
		$first_comment_id = $this->create_discussion( $first, $set->locale, $author, 'First feedback' );
		$this->create_discussion( $second, $set->locale, $author, get_comment_link( $first_comment_id ) );

		$this->set_normal_user_as_current();
		$output = $this->dashboard_output( $set->locale );

		$this->assertStringContainsString( 'First string', $output );
		$this->assertStringContainsString( 'Second string', $output );
		$this->assertSame( 2, substr_count( $output, 'First feedback' ) );
	}

	/**
	 * With paged comments the link ends in the comment page rather than in the original id.
	 */
	function test_discussions_dashboard_re_adds_the_first_comment_of_a_bulk_thread_with_paged_comments() {
		$page_comments         = get_option( 'page_comments' );
		$default_comments_page = get_option( 'default_comments_page' );
		update_option( 'page_comments', 1 );
		update_option( 'default_comments_page', 'newest' );

		$author = $this->set_admin_user_as_current();
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$first  = $this->create_original( $set, 0, 'First string' );
		$second = $this->create_original( $set, 0, 'Second string' );
		$first_comment_id = $this->create_discussion( $first, $set->locale, $author, 'First feedback' );
		$link             = get_comment_link( $first_comment_id );
		$this->create_discussion( $second, $set->locale, $author, $link );

		$output = $this->dashboard_output( $set->locale );

		update_option( 'page_comments', $page_comments );
		update_option( 'default_comments_page', $default_comments_page );

		$this->assertStringContainsString( '/comment-page-1#', $link );
		$this->assertStringContainsString( 'Second string', $output );
		$this->assertSame( 2, substr_count( $output, 'First feedback' ) );
	}

	function test_discussions_dashboard_does_not_re_add_a_comment_from_a_hidden_discussion() {
		$author = $this->set_admin_user_as_current();
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$hidden = $this->create_original( $set, -2, 'Hidden string' );
		$public = $this->create_original( $set, 0, 'Public string' );
		$first_comment_id = $this->create_discussion( $hidden, $set->locale, $author, 'Hidden feedback' );
		$this->create_discussion( $public, $set->locale, $author, get_comment_link( $first_comment_id ) );

		$admin_output = $this->dashboard_output( $set->locale );
		$this->assertStringContainsString( 'Hidden feedback', $admin_output );

		$this->set_normal_user_as_current();
		$output = $this->dashboard_output( $set->locale );

		$this->assertStringNotContainsString( 'Hidden string', $output );
		$this->assertStringNotContainsString( 'Hidden feedback', $output );
	}

	/**
	 * Only approved comments are listed and counted.
	 */
	function test_discussions_dashboard_lists_only_approved_comments() {
		$author = $this->set_admin_user_as_current();
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$first  = $this->create_original( $set, 0, 'First string' );
		$second = $this->create_original( $set, 0, 'Second string' );
		$this->create_discussion( $first, $set->locale, $author, 'Approved feedback' );
		$this->create_discussion( $second, $set->locale, $author, 'Pending feedback', array( 'comment_approved' => 0 ) );

		$this->set_normal_user_as_current();
		$output = $this->dashboard_output( $set->locale );

		$this->assertStringContainsString( 'Approved feedback', $output );
		$this->assertStringNotContainsString( 'Pending feedback', $output );
		$this->assertStringNotContainsString( 'Second string', $output );
		$this->assertStringContainsString( 'All&nbsp;(1)', $output );
	}

	/**
	 * Only an approved comment is re-added for a bulk thread.
	 */
	function test_discussions_dashboard_re_adds_only_an_approved_comment() {
		$author = $this->set_admin_user_as_current();
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$first  = $this->create_original( $set, 0, 'First string' );
		$second = $this->create_original( $set, 0, 'Second string' );
		$first_comment_id = $this->create_discussion( $first, $set->locale, $author, 'Pending feedback', array( 'comment_approved' => 0 ) );
		$this->create_discussion( $second, $set->locale, $author, get_comment_link( $first_comment_id ) );

		$this->set_normal_user_as_current();
		$output = $this->dashboard_output( $set->locale );

		$this->assertStringNotContainsString( 'Pending feedback', $output );
	}

	/**
	 * Only a comment from the dashboard's locale is re-added for a bulk thread.
	 */
	function test_discussions_dashboard_re_adds_only_a_comment_from_its_locale() {
		$author    = $this->set_admin_user_as_current();
		$set       = $this->factory->translation_set->create_with_project_and_locale();
		$other_set = $this->factory->translation_set->create_with_project_and_locale();
		$first     = $this->create_original( $set, 0, 'First string' );
		$second    = $this->create_original( $set, 0, 'Second string' );
		$first_comment_id = $this->create_discussion( $first, $other_set->locale, $author, 'Other locale feedback' );
		$this->create_discussion( $second, $set->locale, $author, get_comment_link( $first_comment_id ) );

		$this->set_normal_user_as_current();
		$output = $this->dashboard_output( $set->locale );

		$this->assertStringNotContainsString( 'Other locale feedback', $output );
	}

	/**
	 * A bulk-thread link is only followed to a comment on the original it names.
	 */
	function test_discussions_dashboard_re_adds_only_a_comment_on_the_original_the_link_names() {
		$author = $this->set_admin_user_as_current();
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$first  = $this->create_original( $set, 0, 'First string' );
		$second = $this->create_original( $set, 0, 'Second string' );
		$third  = $this->create_original( $set, 0, 'Third string' );
		$first_comment_id = $this->create_discussion( $first, $set->locale, $author, 'First feedback' );
		$link             = GP_Route_Translation_Helpers::get_permalink( $set->project->path, $third->id ) . '#comment-' . $first_comment_id;
		$this->create_discussion( $second, $set->locale, $author, $link );

		$this->set_normal_user_as_current();
		$output = $this->dashboard_output( $set->locale );

		$this->assertStringNotContainsString( 'Second string', $output );
	}

	function test_comment_form_submission_on_a_hidden_discussion_is_refused() {
		$author = $this->set_admin_user_as_current();
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$hidden = $this->create_original( $set, -2, 'Hidden string' );
		$this->create_discussion( $hidden, $set->locale, $author );
		$post_id = Helper_Translation_Discussion::get_or_create_shadow_post_id( $hidden->id );

		$this->set_normal_user_as_current();

		$this->expectException( 'WPDieException' );
		wp_handle_comment_submission( array( 'comment_post_ID' => $post_id, 'comment' => 'From the form' ) );
	}

	function test_discussions_dashboard_skips_a_discussion_whose_original_no_longer_exists() {
		$author  = $this->set_admin_user_as_current();
		$set     = $this->factory->translation_set->create_with_project_and_locale();
		$deleted = $this->create_original( $set, 0, 'Deleted string' );
		$public  = $this->create_original( $set, 0, 'Public string' );
		$this->create_discussion( $deleted, $set->locale, $author );
		$this->create_discussion( $public, $set->locale, $author );
		$deleted->delete();

		$output = $this->dashboard_output( $set->locale );

		$this->assertEquals( 'discussions-dashboard', $this->route->loaded_template );
		$this->assertStringContainsString( 'Public string', $output );
		$this->assertStringNotContainsString( 'Deleted string', $output );
	}
}
