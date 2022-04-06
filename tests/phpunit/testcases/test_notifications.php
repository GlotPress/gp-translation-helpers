<?php

class GP_Test_Notifications extends GP_UnitTestCase {

	private $set;
	private $user1_id;
	private $user2_id;
	private $user3_id;
	private $translation;

	function setUp() {
		parent::setUp();

		$object_type = GP::$validator_permission->object_type;
		$this->user1_id = $this->factory->user->create();
		$user1_id_data = get_user_by( 'id', $this->user1_id );
		
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
	}

	function test_reply_notification() {
		$post_id = Helper_Translation_Discussion::get_shadow_post( $this->translation->original_id );

		$that = $this;
		$counter = 0;
		add_filter(
			'pre_wp_mail',
			function ( $empty, $atts ) use( $that, &$counter ) {
				if( $counter === 0 ){
					$counter++;	
					$that->assertEquals( $atts['headers'][1], 'Bcc: ' . get_user_by('id', $this->user1_id)->data->user_email );
				} else {
					$that->assertEquals( $atts['headers'][1], 'Bcc: ' . get_user_by('id', $this->user1_id)->data->user_email );
					$that->assertEquals( $atts['headers'][2], 'Bcc: ' . get_user_by('id', $this->user2_id)->data->user_email );
				}
				return true;

			}, 10, 2
		);

		$comment_id = $this->create_comment( $this->user1_id, $post_id, 'Testing a comment.', 0);

		wp_set_current_user( $this->user2_id );
		$comment_reply_id = $this->create_comment( $this->user2_id, $post_id, 'Reply to first comment.', $comment_id);
		
		wp_set_current_user( $this->user3_id );
		$comment_reply_2_id = $this->create_comment( $this->user3_id, $post_id, 'Reply to first reply.', $comment_reply_id);

		do_action( 'rest_after_insert_comment', get_comment( $comment_reply_id ), null, null );
		do_action( 'rest_after_insert_comment', get_comment( $comment_reply_2_id ), null, null );


	}

	

	function create_comment( $user_id, $post_id, $comment_content, $comment_parent_id ){
		return wp_insert_comment(
			array(
				'comment_content' => $comment_content,
				'comment_post_ID' => $post_id,
				'comment_parent'  => $comment_parent_id,
				'comment_author_email' => get_user_by('id', $user_id)->data->user_email,
				'user_id'         => $user_id,
				'comment_meta'    => array(
					'reject_reason'  => 1,
					'translation_id' => $this->translation->id,
					'locale'         => 'es',
				),
			)
		);
	}
}
