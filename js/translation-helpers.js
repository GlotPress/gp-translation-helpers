$gp.translation_helpers = ( // eslint-disable-line no-undef
	function( $ ) {
		return {
			init: function( table, fetchNow ) {
				$gp.translation_helpers.table = table; // eslint-disable-line no-undef
				$gp.translation_helpers.install_hooks(); // eslint-disable-line no-undef
				if ( fetchNow ) {
					$gp.translation_helpers.fetch( false, $( '.translations' ) ); // eslint-disable-line no-undef
				}
			},
			install_hooks: function() {
				$( $gp.translation_helpers.table ) // eslint-disable-line no-undef
					.on( 'beforeShow', '.editor', $gp.translation_helpers.hooks.initial_fetch ) // eslint-disable-line no-undef
					.on( 'click', '.helpers-tabs li', $gp.translation_helpers.hooks.tab_select ) // eslint-disable-line no-undef
					.on( 'click', 'a.comment-reply-link', $gp.translation_helpers.hooks.reply_comment_form ); // eslint-disable-line no-undef
			},
			initial_fetch: function( $element ) {
				var $helpers = $element.find( '.translation-helpers' );

				if ( $helpers.hasClass( 'loaded' ) || $helpers.hasClass( 'loading' ) ) {
					return;
				}

				$gp.translation_helpers.fetch( false, $element ); // eslint-disable-line no-undef
			},
			fetch: function( which, $element ) {
				var $helpers;
				if ( $element ) {
					$helpers = $element.find( '.translation-helpers' );
				} else {
					$helpers = $( '.editor:visible, .translations' ).find( '.translation-helpers' ).first();
				}

				var originalId = $helpers.parent().attr( 'row' ); // eslint-disable-line vars-on-top
				var replytocom = $helpers.parent().attr( 'replytocom' ); // eslint-disable-line vars-on-top
				var requestUrl = $gp_translation_helpers_settings.th_url + originalId + '?nohc'; // eslint-disable-line

				if ( which ) {
					requestUrl = requestUrl + '&helper=' + which;
				}
				requestUrl = requestUrl + '&replytocom=' + replytocom;

				$helpers.addClass( 'loading' );

				$.getJSON(
					requestUrl,
					function( data ) {
						$helpers.addClass( 'loaded' ).removeClass( 'loading' );
						$.each( data, function( id, result ) {
							jQuery( '.helpers-tabs li[data-tab="' + id + '"]' ).find( '.count' ).text( '(' + result.count + ')' );
							$( '#' + id ).find( '.loading' ).remove();
							$( '#' + id ).find( '.async-content' ).html( result.content );
						} );
					}
				);
			},
			tab_select: function( $tab ) {
				var tabId = $tab.attr( 'data-tab' );

				$tab.siblings().removeClass( 'current' );
				$tab.parents( '.translation-helpers ' ).find( '.helper' ).removeClass( 'current' );

				$tab.addClass( 'current' );
				$( '#' + tabId ).addClass( 'current' );
			},
			reply_comment_form: function( $comment ) {
				var commentId = $comment.attr( 'data-commentid' );
				$( '#comment-reply-' + commentId ).toggle().find( 'textarea' ).focus();
				if ( 'Reply' === $comment.text() ) {
					$comment.text( 'Cancel Reply' );
				} else {
					$comment.text( 'Reply' );
				}
			},
			hooks: {
				initial_fetch: function() {
					$gp.translation_helpers.initial_fetch( $( this ) ); // eslint-disable-line no-undef
					return false;
				},
				tab_select: function() {
					$gp.translation_helpers.tab_select( $( this ) ); // eslint-disable-line no-undef
					return false;
				},
				reply_comment_form: function( event ) {
					event.preventDefault();
					$gp.translation_helpers.reply_comment_form( $( this ) ); // eslint-disable-line no-undef
					return false;
				},
			},
		};
	}( jQuery )
);

jQuery( function( $ ) {
	$gp.translation_helpers.init( $( '.translations' ), true ); // eslint-disable-line no-undef
	if ( typeof window.newShowFunctionAttached === 'undefined' ) { // eslint-disable-line
		window.newShowFunctionAttached = true; // eslint-disable-line
		var _oldShow = $.fn.show; // eslint-disable-line vars-on-top
		$.fn.show = function( speed, oldCallback ) {
			return $( this ).each( function() {
				var obj = $( this ),
					newCallback = function() {
						if ( $.isFunction( oldCallback ) ) {
							oldCallback.apply( obj );
						}
					};

				obj.trigger( 'beforeShow' );
				_oldShow.apply( obj, [ speed, newCallback ] );
			} );
		};
	}
} );
