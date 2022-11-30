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
		$id                = $defined_vars['translation']->row_id;
		$discussion_tab    = '<div class="meta discussion" id="sidebar-div-discussion-' . $id . '" style="display: none;">Discussion tab</div>';
		$history_tab       = '<div class="meta history" id="sidebar-div-history-' . $id . '" style="display: none;">History tab</div>';
		$other_locales_tab = '<div class="meta other-locales" id="sidebar-div-other-locales-' . $id . '" style="display: none;">Other locales tab</div>';
		$tabs              = '<nav class="nav-sidebar">';
		$tabs             .= '<ul class="sidebar-tabs">';
		$tabs             .= '	<li class="current" data-tab="sidebar-tab-meta-' . $id . '">Meta</li>';
		$tabs             .= '	<li data-tab="sidebar-tab-discussion-' . $id . '">Discuss<span class="count">(0)</span></li>';
		$tabs             .= '	<li data-tab="sidebar-tab-history-' . $id . '">History<span class="count">(0)</span></li>';
		$tabs             .= '	<li data-tab="sidebar-tab-other-locales-' . $id . '">Other locales<span class="count">(0)</span></li>';
		$tabs             .= '</ul>';

		$tabs .= $meta_sidebar;
		$tabs .= $discussion_tab;
		$tabs .= $history_tab;
		$tabs .= $other_locales_tab;
		$tabs .= '</nav>';

		return $tabs;
	}

}
