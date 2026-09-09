<?php
/**
 * REST controller for the discussion shadow posts.
 *
 * @package gp-translation-helpers
 */

/**
 * Applies the visibility of the original to the shadow post in the REST API.
 *
 * Core asks check_read_permission() before it serves the shadow post and, through
 * the comments controller, before it lists, serves or creates a comment on it.
 * Nothing reads the collection, the discussion form only posts comments.
 */
class GTH_Shadow_Post_REST_Controller extends WP_REST_Posts_Controller {

	/**
	 * Refuses the collection of shadow posts.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return new WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to list discussions.', 'glotpress' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Allows reading a shadow post only when the current user may view its original.
	 *
	 * @param WP_Post $post The shadow post.
	 *
	 * @return bool
	 */
	public function check_read_permission( $post ) {
		return parent::check_read_permission( $post ) && Helper_Translation_Discussion::can_view_shadow_post( $post->ID );
	}
}
