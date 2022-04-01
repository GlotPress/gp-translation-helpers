<?php
/**
 * Routes: GP_Notifications class
 *
 * Manages the notifications of the plugin.
 *
 * @package gp-translation-helpers
 * @since 0.0.2
 */
class GP_Notifications {
	/**
	 * Sends notifications when a new comment in the discussion is stored using the WP REST API.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment      $comment  Inserted or updated comment object.
	 * @param WP_REST_Request $request  Request object.
	 * @param bool            $creating True when creating a comment, false when updating.
	 *
	 * @return void
	 */
	public static function init( WP_Comment $comment, $request, $creating ) {
		$post = get_post( $comment->comment_post_ID );
		if ( Helper_Translation_Discussion::POST_TYPE === $post->post_type ) {
			if ( ( '1' === $comment->comment_approved ) || ( 'approve' === $comment->comment_approved ) ) {
				$comment_meta = get_comment_meta( $comment->comment_ID );
				if ( ( '0' !== $comment->comment_parent ) ) { // Notify to the thread only if the comment is in a thread.
					self::send_emails_to_thread_commenters( $comment, $comment_meta );
				}
				$root_comment      = self::get_root_comment_in_a_thread( $comment );
				$root_comment_meta = get_comment_meta( $root_comment->comment_ID );
				if ( array_key_exists( 'comment_topic', $root_comment_meta ) ) {
					switch ( $root_comment_meta['comment_topic'][0] ) {
						case 'typo':
						case 'context': // Notify to the GlotPress admins.
							self::send_emails_to_gp_admins( $comment, $comment_meta );
							break;
						case 'question': // Notify to the project validator.
							self::send_emails_to_validators( $comment, $comment_meta );
							break;
					}
				}
			}
		}
	}

	/**
	 * Sends notifications when a comment changes its status to "approved".
	 *
	 * @since 0.0.2
	 *
	 * @param int|string $new_status The new comment status.
	 * @param int|string $old_status The old comment status.
	 * @param WP_Comment $comment    The comment object.
	 *
	 * @return void
	 */
	public static function on_comment_status_change( $new_status, $old_status, WP_Comment $comment ) {
		$post = get_post( $comment->comment_post_ID );
		if ( Helper_Translation_Discussion::POST_TYPE === $post->post_type ) {
			if ( $old_status != $new_status ) {
				if ( ( 'approved' === $new_status ) ) {
					self::init( $comment, '', '' );
				}
			}
		}
	}

	/**
	 * Sends an email to the users that have commented on the thread, except to the last comment author.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment      The comment object.
	 * @param array      $comment_meta The meta values for the comment.
	 *
	 * @return void
	 */
	public static function send_emails_to_thread_commenters( WP_Comment $comment, array $comment_meta ) {
		$parent_comments = self::get_parent_comments( $comment->comment_parent );
		$emails          = self::get_emails_from_the_comments( $parent_comments, $comment->comment_author_email );
		/**
		 * Filters the emails in a thread.
		 *
		 * @since 0.0.2
		 *
		 * @param array      $emails       The emails in the thread.
		 * @param WP_Comment $comment      The comment object.
		 * @param array      $comment_meta The meta values for the comment.
		 */
		$emails = apply_filters( 'gp_notification_email_commenters', $emails, $comment, $comment_meta );
		self::send_emails( $comment, $comment_meta, $emails );
	}

	/**
	 * Sends an email to the GlotPress admins.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment      The comment object.
	 * @param array      $comment_meta The meta values for the comment.
	 *
	 * @return void
	 */
	public static function send_emails_to_gp_admins( WP_Comment $comment, array $comment_meta ) {
		$emails = self::get_emails_from_the_gp_admins( $comment, $comment_meta );
		self::send_emails( $comment, $comment_meta, $emails );
	}

	/**
	 * Sends an email to the all the project validators.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment      The comment object.
	 * @param array      $comment_meta The meta values for the comment.
	 *
	 * @return void
	 */
	public static function send_emails_to_validators( WP_Comment $comment, array $comment_meta ) {
		$emails  = array();
		$project = self::get_project_to_translate( $comment );
		$emails  = self::get_emails_from_the_validators( $project->path );
		/**
		 * Filters the validators' emails.
		 *
		 * @since 0.0.2
		 *
		 * @param array      $emails       The emails in the thread.
		 * @param WP_Comment $comment      The comment object.
		 * @param array      $comment_meta The meta values for the comment.
		 */
		$emails                 = apply_filters( 'gp_notification_email_validators', $emails, $comment, $comment_meta );
		$parent_comments        = self::get_parent_comments( $comment->comment_ID ); // Includes the current comment.
		$emails_from_the_thread = self::get_emails_from_the_comments( $parent_comments, '' );
		// Set the emails array as empty if one validator has a comment in the thread or if one validator is the commenter, to avoid sending the email to all validators.
		if ( true !== empty( array_intersect( $emails, $emails_from_the_thread ) ) ) {
			$emails = array();
		}
		self::send_emails( $comment, $comment_meta, $emails );
	}

	/**
	 * Returns the comments in the thread, including the last one.
	 *
	 * @since 0.0.2
	 *
	 * @param int $comment_id Last comment of the thread.
	 *
	 * @return array The comments in the thread.
	 */
	public static function get_parent_comments( int $comment_id ): array {
		$comments = array();
		$comment  = get_comment( $comment_id );
		if ( ( isset( $comment ) ) && ( 0 != $comment->comment_parent ) ) {
			$comments = self::get_parent_comments( $comment->comment_parent );
		}
		if ( ! is_null( $comment ) ) {
			$comments[] = $comment;
		}
		return $comments;
	}

	/**
	 * Gets the emails to be notified from the thread comments.
	 *
	 * Removes the second parameter from the returned array if it is found.
	 *
	 * @since 0.0.2
	 *
	 * @param array  $comments        Array with the parent comments to the posted comment.
	 * @param string $email_to_remove Email from the posted comment.
	 *
	 * @return array The emails to be notified from the thread comments.
	 */
	public static function get_emails_from_the_comments( array $comments, string $email_to_remove ): array {
		$emails = array();
		foreach ( $comments as $comment ) {
			$emails[] = $comment->comment_author_email;
		}
		$emails = array_unique( $emails );
		if ( ( $key = array_search( $email_to_remove, $emails ) ) !== false ) {
			unset( $emails[ $key ] );
		}
		return $emails;
	}

	/**
	 * Gets the emails of the validators of a project.
	 *
	 * @since 0.0.2
	 *
	 * @param string $project_path The project path.
	 *
	 * @return array The emails of the validators for the given project.
	 */
	public static function get_emails_from_the_validators( string $project_path ): array {
		$emails  = array();
		$project = GP::$project->by_path( $project_path );

		$path_to_root = array_slice( $project->path_to_root(), 1 );
		$permissions  = GP::$validator_permission->by_project_id( $project->id );
		$cmp_fn       = function( $x, $y ) {
			return strcmp( $x->locale_slug, $y->locale_slug );
		};
		usort( $permissions, $cmp_fn );
		$parent_permissions = array();

		foreach ( $path_to_root as $parent_project ) {
			$this_parent_permissions = GP::$validator_permission->by_project_id( $parent_project->id );
			usort( $this_parent_permissions, $cmp_fn );
			foreach ( $this_parent_permissions as $permission ) {
				$permission->project = $parent_project;
			}
			$parent_permissions = array_merge( $parent_permissions, (array) $this_parent_permissions );
		}
		// We can't join on users table.
		foreach ( array_merge( (array) $permissions, (array) $parent_permissions ) as $permission ) {
			$permission->user = get_user_by( 'id', $permission->user_id );
			$emails[]         = $permission->user->data->user_email;
		}

		return $emails;
	}

	/**
	 * Gets the emails from the GlotPress admins.
	 *
	 * @param WP_Comment $comment      The comment object.
	 * @param array      $comment_meta The meta values for the comment.
	 *
	 * @return array The GlotPress admins' emails.
	 */
	public static function get_emails_from_the_gp_admins( WP_Comment $comment, array $comment_meta ):array {
		global $wpdb;
		/**
		 * Filters the validators' emails.
		 *
		 * @since 0.0.2
		 *
		 * @param array      $emails       The emails in the thread.
		 * @param WP_Comment $comment      The comment object.
		 * @param array      $comment_meta The meta values for the comment.
		 */
		$emails = array();
		$output = array();
		$emails = apply_filters( 'gp_notification_email_admins', $emails, $comment, $comment_meta );
		if ( ! empty( $emails ) ) {
			return $emails;
		}

		try {
			$db_emails = $wpdb->get_results(
				$wpdb->prepare(
					"
			SELECT user_email FROM {$wpdb->users} 
			INNER JOIN {$wpdb->gp_permissions}
			ON {$wpdb->users}.ID = {$wpdb->gp_permissions}.user_id 
			WHERE action='admin'"
				),
				ARRAY_N
			);
			foreach ( $db_emails as $email ) {
				$emails[] = $email[0];
			}
			$parent_comments        = self::get_parent_comments( $comment->comment_parent );
			$emails_from_the_thread = self::get_emails_from_the_comments( $parent_comments, '' );
			// Set the emails array as empty if one admin has a comment in the thread.
			if ( true !== empty( array_intersect( $emails, $emails_from_the_thread ) ) || ( in_array( $comment->comment_author_email, $emails ) ) ) {
				$emails = array();
			}
			return $emails;
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Sends an email to all the email addresses.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment      The comment object.
	 * @param array      $comment_meta The meta values for the comment.
	 * @param array      $emails       The emails that will receive the notification.
	 *
	 * @return bool Whether the email has been sent or not.
	 */
	public static function send_emails( WP_Comment $comment, array $comment_meta, array $emails ): bool {
		/**
		 * Filters the email addresses before sending the notifications.
		 *
		 * @since 0.0.2
		 *
		 * @param array $emails The emails to be notified.
		 */
		$emails = apply_filters( 'gp_notification_before_send_emails', $emails );
		if ( ( null === $comment ) || ( null === $comment_meta ) || ( empty( $emails ) ) ) {
			return false;
		}
		$emails  = self::remove_commenter_email( $comment, $emails );
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);
		/**
		 * Filters the email headers.
		 *
		 * @since 0.0.2
		 *
		 * @param array $headers The email headers.
		 */
		$headers = apply_filters( 'gp_notification_email_headers', $headers );
		$subject = esc_html__( 'New comment in a translation discussion' );
		$body    = self::get_email_body( $comment, $comment_meta );
		foreach ( $emails as $email ) {
			$headers[] = 'Bcc: ' . $email;
		}
		wp_mail( '', $subject, $body, $headers );
		return true;
	}

	/**
	 * Creates the email body message.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment      The comment object.
	 * @param array      $comment_meta The meta values for the comment.
	 *
	 * @return string|null
	 */
	public static function get_email_body( WP_Comment $comment, array $comment_meta ): string {
		$project  = self::get_project_to_translate( $comment );
		$original = self::get_original( $comment );
		$output   = '';
		/**
		 * Filters the content of the email at the beginning of the function that gets its content.
		 *
		 * @since 0.0.2
		 *
		 * @param string     $output       The content of the email.
		 * @param WP_Comment $comment      The comment object.
		 * @param array      $comment_meta The meta values for the comment.
		 */
		$output  = apply_filters( 'gp_notification_pre_email_body', $output, $comment, $comment_meta );
		$output .= esc_html__( 'Hi:' );
		$output .= '<br><br>';
		$output .= esc_html__( 'There is a new comment in a discussion in the GlotPress translation system installed at ' );
		$output .= gp_plugin_url();
		$output .= esc_html__( ' that may be of interest to you.' );
		$output .= '<br>';
		$output .= esc_html__( 'It would be nice if you have some time to review this comment and reply to it if needed.' );
		$output .= '<br><br>';
		$url     = GP_Route_Translation_Helpers::get_permalink( $project->path, $original->id );
		$output .= '- <strong>' . esc_html__( 'Discussion URL: ' ) . '</strong><a href="' . $url . '">' . $url . '</a><br>';
		if ( array_key_exists( 'locale', $comment_meta ) && ( ! empty( $comment_meta['locale'][0] ) ) ) {
			$output .= '- <strong>' . esc_html__( 'Locale: ' ) . '</strong>' . esc_html( $comment_meta['locale'][0] ) . '<br>';
		}
		$output .= '- <strong>' . esc_html__( 'Original string: ' ) . '</strong>' . esc_html( $original->singular ) . '<br>';
		if ( array_key_exists( 'translation_id', $comment_meta ) && ( 0 != $comment_meta['translation_id'][0] ) ) {
			$translation_id = $comment_meta['translation_id'][0];
			$translation    = GP::$translation->get( $translation_id );
			// todo: add the plurals.
			if ( ! is_null( $translation ) ) {
				$output .= '- <strong>' . esc_html__( 'Translation string: ' ) . '</strong>' . esc_html( $translation->translation_0 ) . '<br>';
			}
		}
		$output .= '- <strong>' . esc_html__( 'Comment: ' ) . '</strong>' . esc_html( $comment->comment_content ) . '</pre>';
		$output .= '<br><br>';
		$output .= esc_html__( 'Have a nice day' );
		$output .= '<br><br>';
		$output .= esc_html__( 'This is an automated message. Please, do not reply directly to this email.' );
		/**
		 * Filters the content of the email at the end of the function that gets its content.
		 *
		 * @since 0.0.2
		 *
		 * @param string     $output       The content of the email.
		 * @param WP_Comment $comment      The comment object.
		 * @param array      $comment_meta The meta values for the comment.
		 */
		$output = apply_filters( 'gp_notification_post_email_body', $output, $comment, $comment_meta );
		return $output;
	}

	/**
	 * Gets the root comment in a thread.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment The comment object.
	 *
	 * @return WP_Comment The root comment in the thread.
	 */
	public static function get_root_comment_in_a_thread( WP_Comment $comment ): WP_Comment {
		$comments = self::get_parent_comments( $comment->comment_ID );
		foreach ( $comments as $item ) {
			if ( 0 == $item->comment_parent ) {
				return $item;
			}
		}
		return $comment;
	}

	/**
	 * Removes the commenter email from the emails to be notified.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment The comment object.
	 * @param array      $emails  A list of emails.
	 *
	 * @return array The list of emails without the commenter's email.
	 */
	public static function remove_commenter_email( WP_Comment $comment, array $emails ): array {
		if ( ( $key = array_search( $comment->comment_author_email, $emails ) ) !== false ) {
			unset( $emails[ $key ] );
		}
		return array_values( $emails );
	}

	/**
	 * Gets the project that the translated string belongs to.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment The comment object.
	 *
	 * @return GP_Project|bool The project that the translated string belongs to.
	 */
	private static function get_project_to_translate( WP_Comment $comment ) {
		$post_id = $comment->comment_post_ID;
		$terms   = wp_get_object_terms( $post_id, Helper_Translation_Discussion::LINK_TAXONOMY, array( 'number' => 1 ) );
		if ( empty( $terms ) ) {
			return false;
		}

		$original   = GP::$original->get( $terms[0]->slug );
		$project_id = $original->project_id;
		$project    = GP::$project->get( $project_id );

		return $project;
	}

	/**
	 * Gets the original string that the translated string belongs to.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment The comment object.
	 *
	 * @return GP_Thing|false The original string that the translated string belongs to.
	 */
	private static function get_original( WP_Comment $comment ) {
		$post_id = $comment->comment_post_ID;
		$terms   = wp_get_object_terms( $post_id, Helper_Translation_Discussion::LINK_TAXONOMY, array( 'number' => 1 ) );
		if ( empty( $terms ) ) {
			return false;
		}

		return GP::$original->get( $terms[0]->slug );
	}
}
