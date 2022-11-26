/* global $gp */
/* eslint camelcase: "off" */
jQuery( function( $ ) {
	$gp.editor.table.on( 'click', '.sidebar-tabs li', function() {
		var tab = $( this );
		var tabId = tab.attr( 'data-tab' );
		var divId = tabId.replace( 'tab', 'div' );
		var originalId = tabId.split( '-' ).pop();
		change_visible_tab( tab );
		change_visible_div( divId, originalId );
	} );

	/**
	 * Hide all tabs and show one of them, the last clicked.
	 *
	 * @param {Object} tab The selected tab.
	 */
	function change_visible_tab( tab ) {
		var tabId = tab.attr( 'data-tab' );
		tab.siblings().removeClass( 'current' );
		tab.parents( '.sidebar-tabs ' ).find( '.helper' ).removeClass( 'current' );
		tab.addClass( 'current' );

		$( '#' + tabId ).addClass( 'current' );
	}

	/**
	 * Hide all divs and show one of them, the last clicked.
	 *
	 * @param {string} tabId      The select tab id.
	 * @param {number} originalId The id of the original string to translate.
	 */
	function change_visible_div( tabId, originalId ) {
		$( '#sidebar-div-meta-' + originalId ).hide();
		$( '#sidebar-div-discussion-' + originalId ).hide();
		$( '#sidebar-div-history-' + originalId ).hide();
		$( '#sidebar-div-other-locales-' + originalId ).hide();
		$( '#' + tabId ).show();
	}
} );
