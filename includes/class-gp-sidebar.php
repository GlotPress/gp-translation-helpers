<?php
/**
 * Routes: GP_Sidebar class
 *
 * Manages the sidebar in the translation rows.
 *
 * @package gp-translation-helpers
 * @since 0.0.2
 */
class GP_Sidebar {

	public static function init() {
		add_filter( 'gp_right_sidebar', array( static::class, 'add_tabs' ), 10, 2 );
	}

	public static function add_tabs( $meta_sidebar, $defined_vars ) {
		$tabs  = '<nav class="nav-sidebar">';
		$tabs .= '<ul class="sidebar-tabs">';
		$tabs .= '	<li class="current" data-tab="sidebar-tab-meta-' . $defined_vars['translation']->original_id . '">Meta</li>';
		$tabs .= '	<li data-tab="sidebar-tab-discuss-' . $defined_vars['translation']->original_id . '">Discuss<span class="count">(5)</span></li>';
		$tabs .= '	<li data-tab="sidebar-tab-history-' . $defined_vars['translation']->original_id . '">History<span class="count">(3)</span></li>';
		$tabs .= '	<li data-tab="sidebar-tab-other-locales-' . $defined_vars['translation']->original_id . '">Other locales<span class="count">(12)</span></li>';
		$tabs .= '</ul>';

		$tabs .= $meta_sidebar;
		$tabs .= '</nav>';

		return $tabs;
	}

}
