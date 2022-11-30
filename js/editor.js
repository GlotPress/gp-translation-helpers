/* global $gp, $gp_translation_helpers_editor */
/* eslint camelcase: "off" */
jQuery( function( $ ) {
	$gp.editor.table.on( 'click', '.sidebar-tabs li', function() {
		var tab = $( this );
		var tabId = tab.attr( 'data-tab' );
		var divId = tabId.replace( 'tab', 'div' );
		var originalId = tabId.replace( /[^\d-]/g, '' ).replace( /^-+/g, '' );
		change_visible_tab( tab );
		change_visible_div( divId, originalId );
	} );

	// When a new translation row is opened (with double click), the tabs (header and content)
	// for this row are updated with the Ajax query.
	$gp.editor.table.on( 'dblclick', 'tr.preview td', function() {
		var originalId = $( this ).parent().attr( 'id' ).substring( 8 );
		var requestUrl = $gp_translation_helpers_editor.translation_helper_url + originalId + '?nohc';
		$.getJSON( requestUrl, function( data ) {
			$( '[data-tab="sidebar-tab-discussion-' + originalId + '"]' ).html( 'Discuss(' + data[ 'helper-translation-discussion-' + originalId ].count + ')' );
			$( '#sidebar-div-discussion-' + originalId ).html( data[ 'helper-translation-discussion-' + originalId ].content );
			$( '[data-tab="sidebar-tab-history-' + originalId + '"]' ).html( 'History(' + data[ 'helper-history-' + originalId ].count + ')' );
			$( '#sidebar-div-history-' + originalId ).html( data[ 'helper-history-' + originalId ].content );
			$( '[data-tab="sidebar-tab-other-locales-' + originalId + '"]' ).html( 'Other locales(' + data[ 'helper-other-locales-' + originalId ].count + ')' );
			$( '#sidebar-div-other-locales-' + originalId ).html( data[ 'helper-other-locales-' + originalId ].content );
		} );
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
