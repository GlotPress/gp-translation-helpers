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
				'gp_discussion_new_comment_typo',
				function ( $option_typo ) {
					return '<option value="typo">Typo in the English text (developers will be notified if they have opt-in)</option>';
				},
				10,
				1
			);
			add_filter(
				'gp_discussion_new_comment_context',
				function ( $option_context ) {
					return '<option value="context">Where does this string appear? (more context) (developers will be notified if they have opt-in)</option>';
				},
				10,
				1
			);
			add_filter(
				'gp_discussion_new_comment_language_question',
				function ( $option_question, $locale_slug ) {
					if ( $locale_slug ) {
						$gp_locale = GP_Locales::by_slug( $locale_slug );
						if ( $gp_locale ) {
							return '<option value="question">Question about translating to ' . esc_html( $gp_locale->english_name ) . '(GTE/PTE/CLPTE will be notified if they have opt-in)</option>';
						}
					}
					return '';
				},
				10,
				2
			);
		}
	}
}
