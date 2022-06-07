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

		$object_type    = GP::$validator_permission->object_type;
		$this->user1_id = $this->factory->user->create();
		$user1_id_data  = get_user_by( 'id', $this->user1_id );

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

	function test_reply_notification() {
		$that    = $this;
		$counter = 0;
		add_filter(
			'pre_wp_mail',
			function ( $empty, $atts ) use ( $that, &$counter ) {
				if ( $counter === 0 ) {
					$counter++;
					$that->assertEquals( $atts['headers'][1], 'Bcc: ' . get_user_by( 'id', $this->user1_id )->data->user_email );
				} else {
					$that->assertEquals( $atts['headers'][1], 'Bcc: ' . get_user_by( 'id', $this->user1_id )->data->user_email );
					$that->assertEquals( $atts['headers'][2], 'Bcc: ' . get_user_by( 'id', $this->user2_id )->data->user_email );
				}
				return true;

			},
			10,
			2
		);

		$comment_id = $this->create_comment( $this->user1_id, $this->post_id, 'Testing a comment.', 0 );

		wp_set_current_user( $this->user2_id );
		$comment_reply_id = $this->create_comment( $this->user2_id, $this->post_id, 'Reply to first comment.', $comment_id );
		wp_set_current_user( $this->user3_id );
		$comment_reply_2_id = $this->create_comment( $this->user3_id, $this->post_id, 'Reply to first reply.', $comment_reply_id );

		do_action( 'rest_after_insert_comment', get_comment( $comment_reply_id ), null, null );
		do_action( 'rest_after_insert_comment', get_comment( $comment_reply_2_id ), null, null );

	}

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
	 * Test that admin gets an email when a comment is made on a translationby an author
	 */
	function test_notify_admin_of_comment() {
		$admin_id = $this->user1_id;
		$admin    = get_user_by( 'id', $admin_id );
		$admin->set_role( 'administrator' );
		$this->assertEquals( 'administrator', $admin->roles[0] );

		$permission = array(
			'user_id'     => $admin_id,
			'action'      => 'admin',
			'project_id'  => $this->set->project_id,
			'locale_slug' => $this->set->locale,
			'set_slug'    => $this->set->slug,
		);
		GP::$validator_permission->create( $permission );

		$author_id = $this->factory->user->create();
		$author    = get_userdata( $author_id );
		$author->set_role( 'author' );

		$this->assertEquals( 'author', $author->roles[0] );

		$that = $this;
		add_filter(
			'pre_wp_mail',
			function ( $empty, $atts ) use ( $that, $admin_id ) {
				$that->assertEquals( $atts['headers'][1], 'Bcc: ' . get_user_by( 'id', $admin_id )->data->user_email );

				return true;
			},
			10,
			2
		);

		wp_set_current_user( $author_id );
		$comment_id = $this->create_comment( $author_id, $this->post_id, 'Testing a comment.', 0 );

		do_action( 'rest_after_insert_comment', get_comment( $comment_id ), null, null );
	}

	/**
	 * Test that admin and author gets an email when a subscriber replies to a comment made by an author
	 */
	function test_notify_admin_author_of_comment_by_subscriber() {
		$admin_id = $this->user1_id;
		$admin    = get_user_by( 'id', $admin_id );
		$admin->set_role( 'administrator' );
		$this->assertEquals( 'administrator', $admin->roles[0] );

		$permission = array(
			'user_id'     => $admin_id,
			'action'      => 'admin',
			'project_id'  => $this->set->project_id,
			'locale_slug' => $this->set->locale,
			'set_slug'    => $this->set->slug,
		);
		GP::$validator_permission->create( $permission );

		$author_id = $this->user2_id;
		$author    = get_user_by( 'id', $author_id );
		$author->set_role( 'author' );
		$this->assertEquals( 'author', $author->roles[0] );

		$subscriber_id = $this->user3_id;
		$subscriber    = get_user_by( 'id', $subscriber_id );
		$subscriber->set_role( 'subscriber' );
		$this->assertEquals( 'subscriber', $subscriber->roles[0] );

		$that    = $this;
		$counter = 0;
		add_filter(
			'pre_wp_mail',
			function ( $empty, $atts ) use ( $that, &$counter, $admin_id, $author_id ) {
				if ( $counter === 0 ) {
					$that->assertEquals( $atts['headers'][1], 'Bcc: ' . get_user_by( 'id', $author_id )->data->user_email );
					$counter++;
				} else {
					$that->assertEquals( $atts['headers'][1], 'Bcc: ' . get_user_by( 'id', $admin_id )->data->user_email );
				}
				return true;
			},
			10,
			2
		);

		wp_set_current_user( $author_id );
		$comment_id = $this->create_comment( $author_id, $this->post_id, 'Testing a comment.', 0 );

		wp_set_current_user( $subscriber_id );
		$comment_reply_id = $this->create_comment( $subscriber_id, $this->post_id, 'Reply to first reply.', $comment_id );

		do_action( 'rest_after_insert_comment', get_comment( $comment_reply_id ), null, null );

	}

	/**
	 * Test that two admins get an email when a comment is made on a translation by an author
	 */
	function test_notify_two_admins_of_comment_by_author() {
		$admin_1_id = $this->user1_id;
		$admin      = get_user_by( 'id', $admin_1_id );
		$admin->set_role( 'administrator' );
		$this->assertEquals( 'administrator', $admin->roles[0] );

		$permission = array(
			'user_id'     => $admin_1_id,
			'action'      => 'admin',
			'project_id'  => $this->set->project_id,
			'locale_slug' => $this->set->locale,
			'set_slug'    => $this->set->slug,
		);
		GP::$validator_permission->create( $permission );

		$admin_2_id = $this->user2_id;
		$admin      = get_user_by( 'id', $admin_2_id );
		$admin->set_role( 'administrator' );
		$this->assertEquals( 'administrator', $admin->roles[0] );

		$permission = array(
			'user_id'     => $admin_2_id,
			'action'      => 'admin',
			'project_id'  => $this->set->project_id,
			'locale_slug' => $this->set->locale,
			'set_slug'    => $this->set->slug,
		);
		GP::$validator_permission->create( $permission );

		$author_id = $this->user3_id;
		$author    = get_user_by( 'id', $author_id );
		$author->set_role( 'author' );
		$this->assertEquals( 'author', $author->roles[0] );

		$that = $this;
		add_filter(
			'pre_wp_mail',
			function ( $empty, $atts ) use ( $that, $admin_1_id, $admin_2_id ) {
					$that->assertEquals( $atts['headers'][1], 'Bcc: ' . get_user_by( 'id', $admin_1_id )->data->user_email );
					$that->assertEquals( $atts['headers'][2], 'Bcc: ' . get_user_by( 'id', $admin_2_id )->data->user_email );

				return true;
			},
			10,
			2
		);

		wp_set_current_user( $author_id );
		$comment_id = $this->create_comment( $author_id, $this->post_id, 'Testing a comment.', 0 );

		do_action( 'rest_after_insert_comment', get_comment( $comment_id ), null, null );

	}

	/**
	 * Test that subscriber and author gets an email when an admin replies to a comment made by a subscriber
	 */
	function test_notify_comment_author_and_subscriber_of_reply_by_admin() {
		$admin_id = $this->user1_id;
		$admin    = get_user_by( 'id', $admin_id );
		$admin->set_role( 'administrator' );
		$this->assertEquals( 'administrator', $admin->roles[0] );

		$permission = array(
			'user_id'     => $admin_id,
			'action'      => 'admin',
			'project_id'  => $this->set->project_id,
			'locale_slug' => $this->set->locale,
			'set_slug'    => $this->set->slug,
		);
		GP::$validator_permission->create( $permission );

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

		$permission = array(
			'user_id'     => $admin_2_id,
			'action'      => 'admin',
			'project_id'  => $this->set->project_id,
			'locale_slug' => $this->set->locale,
			'set_slug'    => $this->set->slug,
		);
		GP::$validator_permission->create( $permission );

		$that = $this;
		add_filter(
			'pre_wp_mail',
			function ( $empty, $atts ) use ( $that, $author_id, $subscriber_id ) {
					$that->assertEquals( $atts['headers'][1], 'Bcc: ' . get_user_by( 'id', $author_id )->data->user_email );
					$that->assertEquals( $atts['headers'][2], 'Bcc: ' . get_user_by( 'id', $subscriber_id )->data->user_email );

				return true;
			},
			10,
			2
		);

		wp_set_current_user( $author_id );
		$comment_id = $this->create_comment( $author_id, $this->post_id, 'Testing a comment.', 0 );

		wp_set_current_user( $subscriber_id );
		$comment_reply_id = $this->create_comment( $subscriber_id, $this->post_id, 'Reply to first reply.', $comment_id );

		wp_set_current_user( $admin_id );
		$subscriber_comment_reply_id = $this->create_comment( $admin_id, $this->post_id, 'Reply to subscriber\'s reply.', $comment_reply_id );

		do_action( 'rest_after_insert_comment', get_comment( $subscriber_comment_reply_id ), null, null );

	}
}
