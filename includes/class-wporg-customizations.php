<?php
/**
 * Routes: WPorg_Customizations class
 *
 * Manages the WPorg customizations.
 *
 * @package gp-translation-helpers
 * @since 0.0.2
 */
class WPorg_GlotPress_Customizations {
	/**
	 * Adds the hooks to modify the options in the select item where we add a new comment in a discussion.
	 *
	 * @since 0.0.2
	 *
	 * @return void
	 */
	public static function init() {
		if ( defined( 'WPORG_TRANSLATE_BLOGID' ) && ( get_current_blog_id() === WPORG_TRANSLATE_BLOGID ) ) {
			add_filter(
				'gp_discussion_new_comment_options',
				function ( $options, $locale_slug ) {
					$optgroup_question = '';
					if ( $locale_slug ) {
						$gp_locale = GP_Locales::by_slug( $locale_slug );
						if ( $gp_locale ) {
							$optgroup_question = '
								<optgroup label="Notify GTE/PTE/CLPTE (if opted-in)">
									<option value="question">Question about translating to ' . esc_html( $gp_locale->english_name ) . '</option>
								</optgroup>';
						}
					}

					return '<select required="" name="comment_topic" id="comment_topic">
								<option value="">Select a topic</option>
								<optgroup label="Notify developers (if opted-in)">
									<option value="typo">Typo in the English text</option>
									<option value="context">Where does this string appear? (more context)</option>
								</optgroup>' .
								$optgroup_question .
							'</select>';
				},
				10,
				2
			);

			add_filter( 'jetpack_mentions_should_load_ui', '__return_true' );

			add_filter(
				'wporg_load_mentions_list',
				function( $result, $comments, $locale, $original_id ) {
					$validator_email_addresses  = WPorg_GlotPress_Notifications::get_validator_email_addresses_for_original_id( $locale, $original_id );
					$commenters_email_addresses = array_values( GP_Notifications::get_commenters_email_addresses( $comments, $validator_email_addresses ) );

					$all_email_addresses = array_merge(
						$validator_email_addresses,
						$commenters_email_addresses
					);

							$users = array_map(
								function( $email ) {
									$user = get_user_by( 'email', $email );
									return array(
										'ID'            => $user->ID,
										'user_login'    => $user->user_login,
										'user_nicename' => $user->user_nicename,
										'display_name'  => '',
										'source'        => array( 'translators' ),
										'image_URL'     => get_avatar_url( $user->ID ),
									);
								},
								$all_email_addresses
							);
							return $users;
				},
				10,
				4
			);

		}
	}
}
