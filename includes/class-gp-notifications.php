<?php
/**
 * Routes: GP_Notifications class
 *
 * Manages the plugin notifications.
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
	 * @param WP_Comment $comment
	 * @param $request
	 * @param $creating
	 *
	 * @return void
	 */
	public static function new_comment( WP_Comment $comment, $request, $creating ) {
		if ( ( '1' === $comment->comment_approved ) || ( 'approve' === $comment->comment_approved ) ) {
			$comment_meta = get_comment_meta( $comment->comment_ID );
			if ( ( '0' !== $comment->comment_parent ) ) {
				$parent_comments  = self::get_parent_comments( $comment->comment_parent );
				$emails_to_notify = self::get_emails_from_the_comments( $parent_comments, $comment->comment_author_email );
				self::send_emails( $comment, $comment_meta, $emails_to_notify );
			}
		}
	}

	/**
	 * Sends notifications when a new comment changes its status to "approve".
	 *
	 * @since 0.0.2
	 *
	 * @return void
	 */
	public static function comment_change_status() {

	}

	/**
	 * Return the comments in the thread, including the last one.
	 *
	 * @since 0.0.2
	 *
	 * @param int $comment_id   Last comment of the thread.
	 *
	 * @return array
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
	 * Returns the emails to be notified from the thread comments.
	 *
	 * Removes the second parameter from the returned array if it is found.
	 *
	 * @since 0.0.2
	 *
	 * @param array|null $comments      Array with the parent comments to the posted comment.
	 * @param string     $email_to_remove   Email from the posted comment
	 *
	 * @return array|null
	 */
	public static function get_emails_from_the_comments( ?array $comments, string $email_to_remove ): ?array {
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
	 * Sends an email to all the email addresses.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment
	 * @param array|null $comment_meta
	 * @param array|null $emails
	 *
	 * @return void
	 */
	public static function send_emails( WP_Comment $comment, ?array $comment_meta, ?array $emails ) {
		foreach ( $emails as $email ) {
			$subject = esc_html__( 'New comment in a translation discussion' );
			$body    = self::get_email_body( $comment, $comment_meta );
			$headers = array(
				'Content-Type: text/html; charset=UTF-8',
				'From: Translating WordPress.org <no-reply@wordpress.org>',
			);

			wp_mail( $email, $subject, $body, $headers );
		}
	}

	/**
	 * Creates the email body message.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment
	 * @param array|null $comment_meta
	 *
	 * @return string|null
	 */
	public static function get_email_body( WP_Comment $comment, ?array $comment_meta ): ?string {
		$output  = esc_html__( 'Hi:' );
		$output .= '<br><br>';
		$output .= esc_html__( 'There is a new comment in a discussion of the WordPress translation system that may be of interest to you.' );
		$output .= '<br>';
		$output .= esc_html__( 'It would be nice if you have some time to review this comment and reply to it if needed.' );
		$output .= '<br><br>';
		if ( array_key_exists( 'locale', $comment_meta ) ) {
			$output .= '- <strong>' . esc_html__( 'Locale: ' ) . '</strong>' . esc_html( $comment_meta['locale'][0] );
			$output .= '<br>';
		}
		if ( array_key_exists( 'translation_id', $comment_meta ) ) {
			$translation_id = $comment_meta['translation_id'][0];
			$translation    = GP::$translation->get( $translation_id );
			$original       = GP::$original->get( $translation->original_id );
			$output        .= '- <strong>' . esc_html__( 'Original string: ' ) . '</strong>' . esc_html( $original->singular ) . '<br>';
			// todo: add the plurals
			if ( ! is_null( $translation ) ) {
				$output .= '- <strong>' . esc_html__( 'Translation string: ' ) . '</strong>' . esc_html( $translation->translation_0 ) . '<br>';
			}
		}
		$output .= '- <strong>' . esc_html__( 'Comment: ' ) . '</strong>' . esc_html( $comment->comment_content ) . '</pre>';
		$output .= '<br><br>';
		$output .= esc_html__( 'Have a nice day' );
		$output .= '<br><br>';
		$output .= esc_html__( 'This is an automated message. Please, do not reply directly to this email.' );

		return $output;
	}

	/**
	 * Gets the general translation editors (GTE) emails for the given locale.
	 *
	 * @since 0.0.2
	 *
	 * @param string $locale Locale slug.
	 *
	 * @return array
	 */
	public function get_gte_emails( string $locale ): array {
		$emails    = array();
		$gp_locale = GP_Locales::by_field( 'slug', $locale );
		if ( ( ! defined( 'WPORG_TRANSLATE_BLOGID' ) ) || ( false === $gp_locale ) ) {
			return $emails;
		}
		$result  = get_sites(
			array(
				'locale'     => $gp_locale->wp_locale,
				'network_id' => WPORG_GLOBAL_NETWORK_ID,
				'path'       => '/',
				'fields'     => 'ids',
				'number'     => '1',
			)
		);
		$site_id = array_shift( $result );
		if ( ! $site_id ) {
			return $emails;
		}

		$users = get_users(
			array(
				'blog_id'     => $site_id,
				'role'        => 'general_translation_editor',
				'count_total' => false,
			)
		);
		foreach ( $users as $user ) {
			$emails[] = $user->data->user_email;
		}

		return $emails;
	}

	/**
	 * Gets the project translation editors (PTE) emails for the given translation_id (from a project) and locale.
	 *
	 * @since 0.0.2
	 *
	 * @param int    $translation_id The id for the translation showed when the comment was made.
	 * @param string $locale         The locale. E.g. 'zh-tw'.
	 *
	 * @return array
	 */
	public static function get_pte_emails_by_project_and_locale( $translation_id, $locale ): array {
		global $wpdb;
		$emails = array();

		$gp_locale = GP_Locales::by_field( 'slug', $locale );
		if ( ( ! defined( 'WPORG_TRANSLATE_BLOGID' ) ) || ( false === $gp_locale ) ) {
			return $emails;
		}

		// Get the main projects, without parent projects
		$main_projects = $wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT
				`id`
			FROM {$wpdb->gp_projects}
			WHERE `parent_project_id` IS NULL"
			),
			ARRAY_N
		);
		$main_projects = array_merge( ...$main_projects );

		$translation = GP::$translation->get( $translation_id );
		$original    = GP::$original->get( $translation->original_id );
		$project_id  = $original->project_id; // 217;// 17;//216;//
		$project     = GP::$project->get( $project_id );

		// If the parent project is not a main project, get the parent project. We need to do this
		// because we have 3 levels of projects. E.g. wp-plugins->akismet->stable and the PTE are
		// assigned to the second level
		if ( ( ! is_null( $project->parent_project_id ) ) && ( ! ( in_array( $project->parent_project_id, $main_projects ) ) ) ) {
			$project = GP::$project->get( $project->parent_project_id );
		}

		// todo: remove the deleted users in the SQL query
		$translation_editors = $wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT
				{$wpdb->wporg_translation_editors}.user_id, 
			    {$wpdb->wporg_translation_editors}.locale
			FROM {$wpdb->wporg_translation_editors}
			WHERE {$wpdb->wporg_translation_editors}.project_id = %d AND
			      {$wpdb->wporg_translation_editors}.locale = %s 
		",
				$project->id,
				$locale
			),
			OBJECT
		);
		foreach ( $translation_editors as $pte ) {
			$emails[] = WP_User::get_data_by( 'id', $pte->user_id )->user_email;
		}
		return $emails;
	}

}
