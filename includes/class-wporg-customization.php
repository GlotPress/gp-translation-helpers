<?php
/**
 * Manages the customizations for the gp-translation-helpers plugin
 *
 * @package gp-translation-helpers
 * @since 0.0.2
 */
class WPorg_GlotPress_Customization {
	/**
	 * Activates the filters that replaces 'rejected' with 'changes requested'
	 * and 'Reject' to 'Request changes'
	 *
	 * @since 0.0.2
	 *
	 * @return void
	 */
	public static function replace_with_changes_requested() {
		add_filter(
			'gettext_glotpress',
			function( $translation, $text ) {
				if ( 'Rejected' === $text ) {
					return 'Changes requested';
				}
				if ( 'Reject' === $text ) {
					return 'Request changes';
				}
				if ( 'Rejected by:' === $text ) {
					return 'Changes requested by:';
				}
				return $translation;
			},
			10,
			2
		);

		add_filter(
			'gettext_with_context_glotpress',
			function( $translation, $text ) {
				if ( 'rejected' === $text ) {
					return 'changes requested';
				}
				if ( 'Reject' === $text ) {
					return 'Request changes';
				}
				if ( 'Rejected by:' === $text ) {
					return 'Changes requested by:';
				}
				return $translation;
			},
			10,
			2
		);
	}
}
