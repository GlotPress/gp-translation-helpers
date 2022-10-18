<?php

class Ajax_Request_Test extends WP_Ajax_UnitTestCase {

	function test_notify_comment_feedback() {
		$gp_test_notifications = new GP_Test_Notifications();
		$gp_test_notifications->setUp();

		$translation = $gp_test_notifications->translation;
		$translation->set_status( 'changesrequested' );

		$this->assertEquals( 'changesrequested', $translation->status );

		$_POST['nonce']                      = wp_create_nonce( 'gp_comment_feedback' );
		$_POST['data']                       = array();
		$_POST['data']['locale_slug']        = 'af';
		$_POST['data']['translation_status'] = $translation->status;
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

	}
}
