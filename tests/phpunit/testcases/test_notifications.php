<?php

class GP_Test_Notifications extends GP_UnitTestCase {

	private $set;
	private $user1;
	private $user2;
	private $translation;

	function setUp() {
		parent::setUp();

		$object_type = GP::$validator_permission->object_type;
		$this->user1 = $this->factory->user->create();
		wp_set_current_user( $this->user1 );

		$this->user2 = $this->factory->user->create();

		$this->set  = $this->factory->translation_set->create_with_project_and_locale();
		$permission = array(
			'user_id'     => $this->user1,
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
		$for_translation = GP::$translation->for_translation( $this->set->project, $this->set, 0, array( 'status' => 'current' ) );

		$post_id = Helper_Translation_Discussion::get_shadow_post( $this->translation->original_id );

		$gp_translation = GP_Translation_Helpers::get_instance();

		$comment_id = wp_insert_comment(
			array(
				'comment_content' => 'Testing a comment.',
				'comment_post_ID' => $post_id,
				'user_id'         => $this->user1,
				'comment_meta'    => array(
					'reject_reason'  => 1,
					'translation_id' => $this->translation->id,
					'locale'         => 'es',
				),
			)
		);

		wp_set_current_user( $this->user2 );
		$comment_reply_id = wp_insert_comment(
			array(
				'comment_content' => 'Reply to testing a comment.',
				'comment_post_ID' => $post_id,
				'comment_parent'  => $comment_id,
				'comment_meta'    => array(
					'reject_reason'  => 1,
					'translation_id' => $this->translation->id,
					'locale'         => 'es',
				),
			)
		);
		add_filter(
			'pre_wp_mail',
			function ( $empty, $atts ) {
			}
		);

	}
}
