<?php

class GP_Test_Notifications extends GP_UnitTestCase {

	private $set;
	private $user1_id;
	private $user2_id;
	private $user3_id;
	private $translation;
	private $post_id;

	function setUp() {
		parent::setUp();

		$this->user1_id = $this->factory->user->create();

		wp_set_current_user( $this->user1_id );

		$this->user2_id = $this->factory->user->create();

		$this->user3_id = $this->factory->user->create();

		$this->set  = $this->factory->translation_set->create_with_project_and_locale();
		$permission = array(
			'user_id'     => $this->user1_id,
			'action'      => 'approve',
			'project_id'  => $this->set->project_id,
			'locale_slug' => $this->set->locale,
			'set_slug'    => $this->set->slug,
		);
		GP::$validator_permission->create( $permission );

		$this->translation = $this->factory->translation->create_with_original_for_translation_set( $this->set );
		// Put the current count already in the cache
		$this->set->current_count();

		$this->translation->set_status( 'current' );
		$this->set->update_status_breakdown(); // Refresh the counts of the object but not the cache

		$this->post_id = wp_insert_post(
			array(
				'post_type'      => Helper_Translation_Discussion::POST_TYPE,
				'tax_input'      => array(
					Helper_Translation_Discussion::LINK_TAXONOMY => array( strval( $this->translation->original_id ) ),
				),
				'post_status'    => Helper_Translation_Discussion::POST_STATUS,
				'post_author'    => 0,
				'comment_status' => 'open',
			)
		);
	}

	/**
	 * Create a comment on a post
	 *
	 * @since 0.0.2
	 *
	 * @param int    $user_id      The user ID.
	 * @param int    $post_id  The post ID.
	 * @param string $comment_content  Body of comment.
	 * @param int    $comment_parent_id The ID of the parent comment or `0` if it doesn't exist.
	 */
	function create_comment( $user_id, $post_id, $comment_content, $comment_parent_id ) {
		return wp_insert_comment(
			array(
				'comment_content'      => $comment_content,
				'comment_post_ID'      => $post_id,
				'comment_parent'       => $comment_parent_id,
				'comment_author_email' => get_user_by( 'id', $user_id )->data->user_email,
				'user_id'              => $user_id,
				'comment_meta'         => array(
					'reject_reason'  => 1,
					'translation_id' => $this->translation->id,
					'locale'         => $this->set->locale,
					'comment_topic'  => 'context',
				),
			)
		);
	}

	/**
	 * Make a user a GlotPress admin
	 *
	 * @since 0.0.2
	 *
	 * @param int $user_id    The user ID.
	 */
	function make_gp_admin( $user_id ) {
		$permission = array(
			'user_id'     => $user_id,
			'action'      => 'admin',
			'project_id'  => $this->set->project_id,
			'locale_slug' => $this->set->locale,
			'set_slug'    => $this->set->slug,
		);
		GP::$validator_permission->create( $permission );
	}

	/**
	 * Test that users who participate in a comment thread gets notification for new replies
	 */
	function test_reply_notification() {
		$pre_wp_mail = new MockAction();
		add_filter( 'pre_wp_mail', array( $pre_wp_mail, 'filter' ), 10, 2 );

		$comment_id = $this->create_comment( $this->user1_id, $this->post_id, 'Testing a comment.', 0 );

		wp_set_current_user( $this->user2_id );
		$comment_reply_id = $this->create_comment( $this->user2_id, $this->post_id, 'Reply to first comment.', $comment_id );
		do_action( 'rest_after_insert_comment', get_comment( $comment_reply_id ), null, null );

		$this->assertSame( 1, $pre_wp_mail->get_call_count() );
		$all_args        = $pre_wp_mail->get_args();
		$first_call_args = $all_args[0];
		$atts            = $first_call_args[1];

		$this->assertEquals( $atts['headers'][1], 'Bcc: ' . get_user_by( 'id', $this->user1_id )->data->user_email );

		wp_set_current_user( $this->user3_id );
		$comment_reply_2_id = $this->create_comment( $this->user3_id, $this->post_id, 'Reply to first reply.', $comment_reply_id );
		do_action( 'rest_after_insert_comment', get_comment( $comment_reply_2_id ), null, null );

		$this->assertSame( 2, $pre_wp_mail->get_call_count() );
		$all_args         = $pre_wp_mail->get_args();
		$second_call_args = $all_args[1];
		$atts             = $second_call_args[1];

		$this->assertEquals( $atts['headers'][1], 'Bcc: ' . get_user_by( 'id', $this->user1_id )->data->user_email );
		$this->assertEquals( $atts['headers'][2], 'Bcc: ' . get_user_by( 'id', $this->user2_id )->data->user_email );
	}

	/**
	 * Test that admin gets an email when a comment is made on a translation by an author
	 */
	function test_notify_admin_of_comment() {
		$admin_id = $this->user1_id;
		$admin    = get_user_by( 'id', $admin_id );
		$admin->set_role( 'administrator' );
		$this->assertEquals( 'administrator', $admin->roles[0] );

		$this->make_gp_admin( $admin_id );

		$author_id = $this->factory->user->create();
		$author    = get_userdata( $author_id );
		$author->set_role( 'author' );

		$this->assertEquals( 'author', $author->roles[0] );

		$pre_wp_mail = new MockAction();
		add_filter( 'pre_wp_mail', array( $pre_wp_mail, 'filter' ), 10, 2 );

		wp_set_current_user( $author_id );
		$comment_id = $this->create_comment( $author_id, $this->post_id, 'Testing a comment.', 0 );

		do_action( 'rest_after_insert_comment', get_comment( $comment_id ), null, null );

		$this->assertSame( 1, $pre_wp_mail->get_call_count() );
		$all_args        = $pre_wp_mail->get_args();
		$first_call_args = $all_args[0];
		$atts            = $first_call_args[1];

		$this->assertEquals( $atts['headers'][1], 'Bcc: ' . get_user_by( 'id', $admin_id )->data->user_email );

	}

	/**
	 * Test that admin and author gets an email when a subscriber replies to a comment made by an author
	 */
	function test_notify_admin_author_of_comment_by_subscriber() {
		$admin_id = $this->user1_id;
		$admin    = get_user_by( 'id', $admin_id );
		$admin->set_role( 'administrator' );
		$this->assertEquals( 'administrator', $admin->roles[0] );

		$this->make_gp_admin( $admin_id );

		$author_id = $this->user2_id;
		$author    = get_user_by( 'id', $author_id );
		$author->set_role( 'author' );
		$this->assertEquals( 'author', $author->roles[0] );

		$subscriber_id = $this->user3_id;
		$subscriber    = get_user_by( 'id', $subscriber_id );
		$subscriber->set_role( 'subscriber' );
		$this->assertEquals( 'subscriber', $subscriber->roles[0] );

		$pre_wp_mail = new MockAction();
		add_filter( 'pre_wp_mail', array( $pre_wp_mail, 'filter' ), 10, 2 );

		wp_set_current_user( $author_id );
		$comment_id = $this->create_comment( $author_id, $this->post_id, 'Testing a comment.', 0 );

		wp_set_current_user( $subscriber_id );
		$comment_reply_id = $this->create_comment( $subscriber_id, $this->post_id, 'Reply to first reply.', $comment_id );

		do_action( 'rest_after_insert_comment', get_comment( $comment_reply_id ), null, null );

		$this->assertSame( 2, $pre_wp_mail->get_call_count() );
		$all_args        = $pre_wp_mail->get_args();
		$first_call_args = $all_args[0];
		$atts            = $first_call_args[1];
		$this->assertEquals( $atts['headers'][1], 'Bcc: ' . get_user_by( 'id', $author_id )->data->user_email );

		$all_args         = $pre_wp_mail->get_args();
		$second_call_args = $all_args[1];
		$atts             = $second_call_args[1];
		$this->assertEquals( $atts['headers'][1], 'Bcc: ' . get_user_by( 'id', $admin_id )->data->user_email );
	}

	/**
	 * Test that two admins get an email when a comment is made on a translation by an author
	 */
	function test_notify_two_admins_of_comment_by_author() {
		$admin_1_id = $this->user1_id;
		$admin      = get_user_by( 'id', $admin_1_id );
		$admin->set_role( 'administrator' );
		$this->assertEquals( 'administrator', $admin->roles[0] );

		$this->make_gp_admin( $admin_1_id );

		$admin_2_id = $this->user2_id;
		$admin      = get_user_by( 'id', $admin_2_id );
		$admin->set_role( 'administrator' );
		$this->assertEquals( 'administrator', $admin->roles[0] );

		$this->make_gp_admin( $admin_2_id );

		$author_id = $this->user3_id;
		$author    = get_user_by( 'id', $author_id );
		$author->set_role( 'author' );
		$this->assertEquals( 'author', $author->roles[0] );

		$pre_wp_mail = new MockAction();
		add_filter( 'pre_wp_mail', array( $pre_wp_mail, 'filter' ), 10, 2 );

		wp_set_current_user( $author_id );
		$comment_id = $this->create_comment( $author_id, $this->post_id, 'Testing a comment.', 0 );

		do_action( 'rest_after_insert_comment', get_comment( $comment_id ), null, null );

		$this->assertSame( 1, $pre_wp_mail->get_call_count() );
		$all_args        = $pre_wp_mail->get_args();
		$first_call_args = $all_args[0];
		$atts            = $first_call_args[1];
		$this->assertEquals( $atts['headers'][1], 'Bcc: ' . get_user_by( 'id', $admin_1_id )->data->user_email );
		$this->assertEquals( $atts['headers'][2], 'Bcc: ' . get_user_by( 'id', $admin_2_id )->data->user_email );
	}

	/**
	 * Test that subscriber and author gets an email when an admin replies to a comment made by a subscriber
	 */
	function test_notify_comment_author_and_subscriber_of_reply_by_admin() {
		$admin_id = $this->user1_id;
		$admin    = get_user_by( 'id', $admin_id );
		$admin->set_role( 'administrator' );
		$this->assertEquals( 'administrator', $admin->roles[0] );

		$this->make_gp_admin( $admin_id );

		$author_id = $this->user2_id;
		$author    = get_user_by( 'id', $author_id );
		$author->set_role( 'author' );
		$this->assertEquals( 'author', $author->roles[0] );

		$subscriber_id = $this->user3_id;
		$subscriber    = get_user_by( 'id', $subscriber_id );
		$subscriber->set_role( 'subscriber' );
		$this->assertEquals( 'subscriber', $subscriber->roles[0] );

		$admin_2_id = $this->factory->user->create();
		$admin_2    = get_user_by( 'id', $admin_2_id );
		$admin_2->set_role( 'administrator' );
		$this->assertEquals( 'administrator', $admin_2->roles[0] );

		$this->make_gp_admin( $admin_2_id );

		$pre_wp_mail = new MockAction();
		add_filter( 'pre_wp_mail', array( $pre_wp_mail, 'filter' ), 10, 2 );

		wp_set_current_user( $author_id );
		$comment_id = $this->create_comment( $author_id, $this->post_id, 'Testing a comment.', 0 );

		wp_set_current_user( $subscriber_id );
		$comment_reply_id = $this->create_comment( $subscriber_id, $this->post_id, 'Reply to first reply.', $comment_id );

		wp_set_current_user( $admin_id );
		$subscriber_comment_reply_id = $this->create_comment( $admin_id, $this->post_id, 'Reply to subscriber\'s reply.', $comment_reply_id );

		do_action( 'rest_after_insert_comment', get_comment( $subscriber_comment_reply_id ), null, null );

		$this->assertSame( 1, $pre_wp_mail->get_call_count() );
		$all_args        = $pre_wp_mail->get_args();
		$first_call_args = $all_args[0];
		$atts            = $first_call_args[1];

		$this->assertEquals( $atts['headers'][1], 'Bcc: ' . get_user_by( 'id', $author_id )->data->user_email );
		$this->assertEquals( $atts['headers'][2], 'Bcc: ' . get_user_by( 'id', $subscriber_id )->data->user_email );

	}

	/**
	 * Test that author gets an email notification when an admin replies to their comment
	 */
	function test_notify_author_of_reply_by_admin() {
		$admin_id = $this->user1_id;
		$admin    = get_user_by( 'id', $admin_id );
		$admin->set_role( 'administrator' );
		$this->assertEquals( 'administrator', $admin->roles[0] );

		$this->make_gp_admin( $admin_id );

		$admin_2_id = $this->factory->user->create();
		$admin_2    = get_user_by( 'id', $admin_2_id );
		$admin_2->set_role( 'administrator' );
		$this->assertEquals( 'administrator', $admin_2->roles[0] );

		$this->make_gp_admin( $admin_2_id );

		$author_id = $this->user2_id;
		$author    = get_user_by( 'id', $author_id );
		$author->set_role( 'author' );
		$this->assertEquals( 'author', $author->roles[0] );

		$pre_wp_mail = new MockAction();
		add_filter( 'pre_wp_mail', array( $pre_wp_mail, 'filter' ), 10, 2 );

		wp_set_current_user( $author_id );
		$comment_id = $this->create_comment( $author_id, $this->post_id, 'Testing a comment.', 0 );

		wp_set_current_user( $admin_id );
		$comment_reply_id = $this->create_comment( $admin_id, $this->post_id, 'Reply to comment.', $comment_id );

		do_action( 'rest_after_insert_comment', get_comment( $comment_reply_id ), null, null );

		$this->assertSame( 1, $pre_wp_mail->get_call_count() );
		$all_args        = $pre_wp_mail->get_args();
		$first_call_args = $all_args[0];
		$atts            = $first_call_args[1];

		$this->assertEquals( $atts['headers'][1], 'Bcc: ' . get_user_by( 'id', $author_id )->data->user_email );

	}

}
