<?php
/**
 * Manages the customizations for the gp-translation-helpers plugin
 *
 * @package gp-translation-helpers
 * @since 0.0.2
 */
class WPorg_GlotPress_Customization {
	/**
	 * Registers and enqueues the custom dotorg css
	 *
	 * @since 0.0.2
	 *
	 * @return void
	 */
	public static function load_dotorg_custom_css() {
		wp_register_style( 'wporg-translation-discussion-css', plugins_url( '/../helpers-assets/css/wporg-translation-discussion.css', __FILE__ ), array(), '0.0.1' );
		gp_enqueue_style( 'wporg-translation-discussion-css' );
	}
}
