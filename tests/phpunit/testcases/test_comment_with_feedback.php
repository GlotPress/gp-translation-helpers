<?php

class Ajax_Request_Test extends WP_Ajax_UnitTestCase {

	/**
	 * @var GP_UnitTest_Factory
	 */
	private $gp;

	public function setUp(): void {
		parent::setUp();

		$this->gp = new GP_UnitTest_Factory();

		// WordPress 6.9+ deprecates seems_utf8(), but GlotPress develop may
		// no longer call it. Dynamically mark it as expected only when called.
		$test = $this;
		add_action(
			'deprecated_function_run',
			static function ( $function ) use ( $test ) {
				if ( 'seems_utf8' === $function ) {
					$test->setExpectedDeprecated( 'seems_utf8' );
				}
			},
			1
		);
	}

	public function tearDown(): void {
		// GP_Notifications collects the related comments of a bulk request in a
		// static property that is only meant to live for one request.
		$related_comments = new ReflectionProperty( 'GP_Notifications', 'related_comments' );
		$related_comments->setAccessible( true );
		$related_comments->setValue( null, array() );

		parent::tearDown();
	}

	private function create_set( $locale_slug, $project = null ) {
		$this->gp->locale->create( array( 'slug' => $locale_slug ) );
		if ( ! $project ) {
			$project = $this->gp->project->create();
		}

		return $this->gp->translation_set->create(
			array(
				'project_id' => $project->id,
				'locale'     => $locale_slug,
				'slug'       => 'default',
			)
		);
	}

	private function create_validator( $set ) {
		$user_id = $this->gp->user->create();
		GP::$validator_permission->create(
			array(
				'user_id'     => $user_id,
				'action'      => 'approve',
				'project_id'  => $set->project_id,
				'locale_slug' => $set->locale,
				'set_slug'    => $set->slug,
			)
		);

		return $user_id;
	}

	private function create_original( $set, $priority = 0, $singular = 'Original string' ) {
		return $this->gp->original->create(
			array(
				'project_id' => $set->project_id,
				'status'     => '+active',
				'singular'   => $singular,
				'priority'   => $priority,
			)
		);
	}

	private function create_translation( $set, $original ) {
		return $this->gp->translation->create(
			array(
				'original_id'        => $original->id,
				'translation_set_id' => $set->id,
				'status'             => 'waiting',
			)
		);
	}

	private function post_feedback( $locale_slug, $original_ids, $translation_ids, $translation_status = null ) {
		$_POST['nonce'] = wp_create_nonce( 'gp_comment_feedback' );
		$_POST['data']  = array(
			'locale_slug'        => $locale_slug,
			'translation_status' => $translation_status ?? array_fill( 0, count( (array) $translation_ids ), 'waiting' ),
			'translation_id'     => $translation_ids,
			'original_id'        => $original_ids,
			'reason'             => array( 'typo' ),
			'comment'            => 'Feedback comment',
		);

		try {
			$this->_handleAjax( 'comment_with_feedback' );
		} catch ( WPAjaxDieContinueException $e ) {
			// wp_send_json_*() ends the request.
		}

		return json_decode( $this->_last_response );
	}

	private function post_create_shadow_post( $temporary_post_id ) {
		$_POST['nonce'] = wp_create_nonce( 'wp_rest' );
		$_POST['data']  = array( 'post' => $temporary_post_id );

		try {
			$this->_handleAjax( 'create_shadow_post' );
		} catch ( WPAjaxDieContinueException $e ) {
			// wp_send_json_*() ends the request.
		}

		return json_decode( $this->_last_response );
	}

	private function rest( $method, $route, array $params = array() ) {
		$request = new WP_REST_Request( $method, $route );
		foreach ( $params as $name => $value ) {
			$request->set_param( $name, $value );
		}

		return rest_get_server()->dispatch( $request );
	}

	private function create_discussion( $original, $user_id, $content = 'A discussion comment' ) {
		$post_id = Helper_Translation_Discussion::get_or_create_shadow_post_id( $original->id );
		$user    = get_user_by( 'id', $user_id );
		wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_content'      => $content,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'user_id'              => $user_id,
				'comment_meta'         => array( 'locale' => 'de' ),
			)
		);

		return $post_id;
	}

	private function comments_on( $original ) {
		$post_id = GP_Notifications::get_post_id( $original->id );
		if ( ! $post_id ) {
			return array();
		}

		return get_comments(
			array(
				'post_id' => $post_id,
				'status'  => 'all',
				'orderby' => 'comment_ID',
				'order'   => 'ASC',
			)
		);
	}

	/**
	 * Test that an email is fired when translation status is updated to changesrequested and also with a feedback.
	 */
	function test_notify_comment_feedback() {
		$pre_wp_mail = new MockAction();
		add_filter( 'pre_wp_mail', array( $pre_wp_mail, 'filter' ), 10, 2 );

		$gp_test_notifications = new GP_Test_Notifications();
		$gp_test_notifications->setUp();

		$translation = $gp_test_notifications->translation;
		$translation->set_status( 'changesrequested' );

		$this->assertEquals( 'changesrequested', $translation->status );

		$_POST['nonce']                      = wp_create_nonce( 'gp_comment_feedback' );
		$_POST['data']                       = array();
		$_POST['data']['locale_slug']        = $translation->translation_set->locale;
		$_POST['data']['translation_status'] = array( $translation->status );
		$_POST['data']['translation_id']     = array( $translation->id );
		$_POST['data']['original_id']        = array( $translation->original_id );
		$_POST['data']['reason']             = 'context';
		$_POST['data']['comment']            = 'test comment';
		try {
			$this->_handleAjax( 'comment_with_feedback' );
		} catch ( Exception $e ) {

		}

		$response = json_decode( $this->_last_response );
		$this->assertEquals( 'success', $response->data );

		$this->assertSame( 1, $pre_wp_mail->get_call_count() );

	}

	function test_feedback_on_a_visible_original_creates_an_approved_comment() {
		$set = $this->create_set( 'de' );
		wp_set_current_user( $this->create_validator( $set ) );
		$original    = $this->create_original( $set, 0, 'Public string' );
		$translation = $this->create_translation( $set, $original );

		$response = $this->post_feedback( 'de', array( $original->id ), array( $translation->id ) );

		$this->assertTrue( $response->success );
		$comments = $this->comments_on( $original );
		$this->assertCount( 1, $comments );
		$this->assertSame( '1', $comments[0]->comment_approved );
		$this->assertEquals( $translation->id, get_comment_meta( $comments[0]->comment_ID, 'translation_id', true ) );
		$this->assertSame( 'de', get_comment_meta( $comments[0]->comment_ID, 'locale', true ) );
	}

	function test_a_request_whose_ids_are_not_arrays_is_rejected() {
		$set = $this->create_set( 'de' );
		wp_set_current_user( $this->create_validator( $set ) );
		$original    = $this->create_original( $set, 0, 'Public string' );
		$translation = $this->create_translation( $set, $original );

		$response = $this->post_feedback( 'de', array( $original->id ), (string) $translation->id );

		$this->assertFalse( $response->success );
		$this->assertCount( 0, $this->comments_on( $original ) );
	}

	function test_each_comment_stores_the_status_of_its_own_translation_only() {
		$set = $this->create_set( 'de' );
		wp_set_current_user( $this->create_validator( $set ) );
		$first        = $this->create_original( $set, 0, 'First string' );
		$second       = $this->create_original( $set, 0, 'Second string' );
		$first_trans  = $this->create_translation( $set, $first );
		$second_trans = $this->create_translation( $set, $second );

		$status   = array(
			'not-a-translation-id' => 'current',
			$first_trans->id       => 'waiting',
			$second_trans->id      => 'fuzzy',
		);
		$response = $this->post_feedback( 'de', array( $first->id, $second->id ), array( $first_trans->id, $second_trans->id ), $status );

		$this->assertTrue( $response->success );
		$first_comment  = $this->comments_on( $first )[0];
		$second_comment = $this->comments_on( $second )[0];
		$this->assertSame( array( $first_trans->id => 'waiting' ), get_comment_meta( $first_comment->comment_ID, 'translation_status', true ) );
		$this->assertSame( array( $second_trans->id => 'fuzzy' ), get_comment_meta( $second_comment->comment_ID, 'translation_status', true ) );
	}

	function test_bulk_feedback_links_the_other_originals_to_the_first_comment() {
		$set = $this->create_set( 'de' );
		wp_set_current_user( $this->create_validator( $set ) );
		$first         = $this->create_original( $set, 0, 'First string' );
		$second        = $this->create_original( $set, 0, 'Second string' );
		$first_trans   = $this->create_translation( $set, $first );
		$second_trans  = $this->create_translation( $set, $second );

		$response = $this->post_feedback( 'de', array( $first->id, $second->id ), array( $first_trans->id, $second_trans->id ) );

		$this->assertTrue( $response->success );
		$first_comments  = $this->comments_on( $first );
		$second_comments = $this->comments_on( $second );
		$this->assertCount( 1, $first_comments );
		$this->assertCount( 1, $second_comments );
		$this->assertSame( 'Feedback comment', $first_comments[0]->comment_content );
		$this->assertSame( get_comment_link( $first_comments[0] ), $second_comments[0]->comment_content );
	}

	function test_feedback_is_rejected_for_a_user_who_cannot_approve_the_translation() {
		wp_set_current_user( $this->gp->user->create() );
		$set         = $this->create_set( 'de' );
		$original    = $this->create_original( $set, 0, 'Public string' );
		$translation = $this->create_translation( $set, $original );

		$response = $this->post_feedback( 'de', array( $original->id ), array( $translation->id ) );

		$this->assertFalse( $response->success );
		$this->assertSame( 0, GP_Notifications::get_post_id( $original->id ) );
	}

	function test_feedback_on_a_hidden_original_is_rejected_for_a_validator_who_cannot_write() {
		$set = $this->create_set( 'de' );
		wp_set_current_user( $this->create_validator( $set ) );
		$hidden      = $this->create_original( $set, -2, 'Hidden string' );
		$translation = $this->create_translation( $set, $hidden );

		$response = $this->post_feedback( 'de', array( $hidden->id ), array( $translation->id ) );

		$this->assertFalse( $response->success );
		$this->assertSame( 0, GP_Notifications::get_post_id( $hidden->id ) );
	}

	function test_feedback_on_a_hidden_original_is_accepted_for_a_user_who_can_write() {
		wp_set_current_user( $this->gp->user->create_admin() );
		$set         = $this->create_set( 'de' );
		$hidden      = $this->create_original( $set, -2, 'Hidden string' );
		$translation = $this->create_translation( $set, $hidden );

		$response = $this->post_feedback( 'de', array( $hidden->id ), array( $translation->id ) );

		$this->assertTrue( $response->success );
		$comments = $this->comments_on( $hidden );
		$this->assertCount( 1, $comments );
		$this->assertSame( '1', $comments[0]->comment_approved );
	}

	function test_a_hidden_original_in_a_bulk_request_creates_no_comments_at_all() {
		$set = $this->create_set( 'de' );
		wp_set_current_user( $this->create_validator( $set ) );
		$public       = $this->create_original( $set, 0, 'Public string' );
		$hidden       = $this->create_original( $set, -2, 'Hidden string' );
		$public_trans = $this->create_translation( $set, $public );
		$hidden_trans = $this->create_translation( $set, $hidden );

		$response = $this->post_feedback( 'de', array( $public->id, $hidden->id ), array( $public_trans->id, $hidden_trans->id ) );

		$this->assertFalse( $response->success );
		$this->assertCount( 0, $this->comments_on( $public ) );
		$this->assertSame( 0, GP_Notifications::get_post_id( $hidden->id ) );
	}

	function test_a_translation_of_another_original_is_rejected() {
		$set = $this->create_set( 'de' );
		wp_set_current_user( $this->create_validator( $set ) );
		$first   = $this->create_original( $set, 0, 'First string' );
		$second  = $this->create_original( $set, 0, 'Second string' );
		$this->create_translation( $set, $first );
		$second_trans = $this->create_translation( $set, $second );

		$response = $this->post_feedback( 'de', array( $first->id ), array( $second_trans->id ) );

		$this->assertFalse( $response->success );
		$this->assertCount( 0, $this->comments_on( $first ) );
		$this->assertCount( 0, $this->comments_on( $second ) );
	}

	function test_a_translation_from_another_locale_is_rejected() {
		$set_de = $this->create_set( 'de' );
		wp_set_current_user( $this->create_validator( $set_de ) );
		$set_es      = $this->create_set( 'es', GP::$project->get( $set_de->project_id ) );
		$original    = $this->create_original( $set_de, 0, 'Public string' );
		$translation = $this->create_translation( $set_es, $original );

		$response = $this->post_feedback( 'de', array( $original->id ), array( $translation->id ) );

		$this->assertFalse( $response->success );
		$this->assertCount( 0, $this->comments_on( $original ) );
	}

	function test_a_request_whose_ids_do_not_pair_up_is_rejected() {
		$set = $this->create_set( 'de' );
		wp_set_current_user( $this->create_validator( $set ) );
		$first       = $this->create_original( $set, 0, 'First string' );
		$second      = $this->create_original( $set, 0, 'Second string' );
		$first_trans = $this->create_translation( $set, $first );

		$response = $this->post_feedback( 'de', array( $first->id, $second->id ), array( $first_trans->id ) );

		$this->assertFalse( $response->success );
		$this->assertCount( 0, $this->comments_on( $first ) );
		$this->assertCount( 0, $this->comments_on( $second ) );
	}

	function test_create_shadow_post_works_for_a_visible_original() {
		wp_set_current_user( $this->gp->user->create() );
		$set      = $this->create_set( 'de' );
		$original = $this->create_original( $set, 0, 'Public string' );

		$response = $this->post_create_shadow_post( Helper_Translation_Discussion::POST_TYPE . $original->id );

		$this->assertTrue( $response->success );
		$this->assertSame( GP_Notifications::get_post_id( $original->id ), (int) $response->data );
	}

	function test_create_shadow_post_is_rejected_for_a_hidden_original_and_a_user_who_cannot_write() {
		wp_set_current_user( $this->gp->user->create() );
		$set    = $this->create_set( 'de' );
		$hidden = $this->create_original( $set, -2, 'Hidden string' );

		$response = $this->post_create_shadow_post( Helper_Translation_Discussion::POST_TYPE . $hidden->id );

		$this->assertFalse( $response->success );
		$this->assertSame( 0, GP_Notifications::get_post_id( $hidden->id ) );
	}

	function test_rest_refuses_the_collection_of_shadow_posts() {
		$set    = $this->create_set( 'de' );
		$public = $this->create_original( $set, 0, 'Public string' );
		$this->create_discussion( $public, $this->gp->user->create_admin() );

		wp_set_current_user( 0 );
		$response = $this->rest( 'GET', '/wp/v2/gth_original' );

		$this->assertContains( $response->get_status(), array( 401, 403 ) );
	}

	function test_rest_does_not_list_comments_of_a_hidden_discussion() {
		$set     = $this->create_set( 'de' );
		$hidden  = $this->create_original( $set, -2, 'Hidden string' );
		$public  = $this->create_original( $set, 0, 'Public string' );
		$admin   = $this->gp->user->create_admin();
		$hidden_post = $this->create_discussion( $hidden, $admin, 'Hidden feedback' );
		$public_post = $this->create_discussion( $public, $admin, 'Public feedback' );

		wp_set_current_user( 0 );
		$by_post = $this->rest( 'GET', '/wp/v2/comments', array( 'post' => $hidden_post ) );
		$this->assertContains( $by_post->get_status(), array( 401, 403 ) );

		$all      = $this->rest( 'GET', '/wp/v2/comments' );
		$contents = wp_list_pluck( wp_list_pluck( $all->get_data(), 'content' ), 'rendered' );
		$this->assertSame( 200, $all->get_status() );
		$this->assertStringNotContainsString( 'Hidden feedback', implode( ' ', $contents ) );

		$public_by_post = $this->rest( 'GET', '/wp/v2/comments', array( 'post' => $public_post ) );
		$this->assertSame( 200, $public_by_post->get_status() );
	}

	function test_rest_refuses_a_comment_on_a_hidden_discussion_for_a_user_who_cannot_write() {
		$set         = $this->create_set( 'de' );
		$hidden      = $this->create_original( $set, -2, 'Hidden string' );
		$hidden_post = $this->create_discussion( $hidden, $this->gp->user->create_admin() );

		wp_set_current_user( $this->gp->user->create() );
		$response = $this->rest( 'POST', '/wp/v2/comments', array( 'post' => $hidden_post, 'content' => 'REST write' ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertCount( 1, $this->comments_on( $hidden ) );
	}

	function test_rest_accepts_a_comment_on_a_visible_discussion() {
		$set         = $this->create_set( 'de' );
		$public      = $this->create_original( $set, 0, 'Public string' );
		$public_post = $this->create_discussion( $public, $this->gp->user->create_admin() );

		wp_set_current_user( $this->gp->user->create() );
		$response = $this->rest( 'POST', '/wp/v2/comments', array( 'post' => $public_post, 'content' => 'REST write' ) );

		$this->assertSame( 201, $response->get_status() );
		$this->assertCount( 2, $this->comments_on( $public ) );
	}

	function test_rest_serves_a_hidden_discussion_to_a_user_who_can_write() {
		$set         = $this->create_set( 'de' );
		$hidden      = $this->create_original( $set, -2, 'Hidden string' );
		$admin       = $this->gp->user->create_admin();
		$hidden_post = $this->create_discussion( $hidden, $admin, 'Hidden feedback' );

		wp_set_current_user( $admin );
		$response = $this->rest( 'GET', '/wp/v2/comments', array( 'post' => $hidden_post ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data() );
	}

	function test_rest_comment_total_does_not_count_a_hidden_discussion() {
		$set    = $this->create_set( 'de' );
		$hidden = $this->create_original( $set, -2, 'Hidden string' );
		$public = $this->create_original( $set, 0, 'Public string' );
		$admin  = $this->gp->user->create_admin();
		$this->create_discussion( $hidden, $admin, 'Hidden feedback' );
		$this->create_discussion( $public, $admin, 'Public feedback' );

		wp_set_current_user( 0 );
		$hidden_search = $this->rest( 'GET', '/wp/v2/comments', array( 'search' => 'Hidden feedback' ) );
		$public_search = $this->rest( 'GET', '/wp/v2/comments', array( 'search' => 'Public feedback' ) );

		$this->assertSame( '0', $hidden_search->get_headers()['X-WP-Total'] );
		$this->assertSame( '0', $public_search->get_headers()['X-WP-Total'], 'Discussion comments stay out of comment queries that do not name a post.' );
		$this->assertCount( 0, $public_search->get_data() );
	}

	function test_rest_excludes_discussions_when_the_same_query_was_cached_without_the_exclusion() {
		$set    = $this->create_set( 'de' );
		$public = $this->create_original( $set, 0, 'Public string' );
		$this->create_discussion( $public, $this->gp->user->create_admin(), 'Public feedback' );

		wp_set_current_user( 0 );
		$query_args = null;
		$spy        = function ( $args ) use ( &$query_args ) {
			$query_args = $args;
			return $args;
		};
		add_filter( 'rest_comment_query', $spy, 30 );
		$this->rest( 'GET', '/wp/v2/comments', array( 'search' => 'Public feedback' ) );
		remove_filter( 'rest_comment_query', $spy, 30 );
		wp_cache_flush();

		// The same query without the exclusion fills the cache with the discussion comment.
		$query_args                 = wp_array_slice_assoc( $query_args, array_keys( ( new WP_Comment_Query() )->query_var_defaults ) );
		$query_args['cache_domain'] = 'core';
		$this->assertCount( 1, ( new WP_Comment_Query( $query_args ) )->comments );

		$response = $this->rest( 'GET', '/wp/v2/comments', array( 'search' => 'Public feedback' ) );

		$this->assertSame( '0', $response->get_headers()['X-WP-Total'] );
		$this->assertCount( 0, $response->get_data() );
	}

	function test_rest_keeps_listing_comments_without_a_post_while_excluding_discussions() {
		$set    = $this->create_set( 'de' );
		$public = $this->create_original( $set, 0, 'Public string' );
		$admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->create_discussion( $public, $admin, 'Public feedback' );
		$orphan_id = wp_insert_comment(
			array(
				'comment_post_ID'  => 0,
				'comment_content'  => 'Orphan comment',
				'comment_approved' => 1,
			)
		);

		wp_set_current_user( $admin );
		$response = $this->rest( 'GET', '/wp/v2/comments' );
		$contents = wp_list_pluck( wp_list_pluck( $response->get_data(), 'content' ), 'rendered' );

		$this->assertContains( $orphan_id, wp_list_pluck( $response->get_data(), 'id' ) );
		$this->assertStringNotContainsString( 'Public feedback', implode( ' ', $contents ) );
		$this->assertSame( '1', $response->get_headers()['X-WP-Total'], 'Only the discussion comment is excluded.' );
	}

	function test_shadow_post_is_linked_to_its_original_regardless_of_the_current_user() {
		$set      = $this->create_set( 'de' );
		$original = $this->create_original( $set, 0, 'Public string' );

		wp_set_current_user( 0 );
		$post_id = Helper_Translation_Discussion::get_or_create_shadow_post_id( $original->id );

		$this->assertGreaterThan( 0, $post_id );
		$this->assertEquals( $original->id, Helper_Translation_Discussion::get_original_from_post_id( $post_id ) );
	}

	function test_create_shadow_post_is_rejected_for_an_unknown_original() {
		wp_set_current_user( $this->gp->user->create() );

		$response = $this->post_create_shadow_post( Helper_Translation_Discussion::POST_TYPE . '987654321' );

		$this->assertFalse( $response->success );
		$this->assertSame( 0, GP_Notifications::get_post_id( 987654321 ) );
	}
}
