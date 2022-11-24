/* global $gp */
jQuery( function( $ ) {
	$gp.editor.table.on( 'click', '.sidebar-tabs li', function() {
		var tab = $( this );
		var tabId = tab.attr( 'data-tab' );
		tab.siblings().removeClass( 'current' );
		tab.parents( '.sidebar-tabs ' ).find( '.helper' ).removeClass( 'current' );

		tab.addClass( 'current' );
		$( '#' + tabId ).addClass( 'current' );
	} );
} );
