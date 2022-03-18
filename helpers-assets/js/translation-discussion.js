jQuery( function( $ ) {
	$( '.helper-translation-discussion' ).on( 'click', '.comments-selector a', function( e ) {
		e.preventDefault();
		$( '.comments-selector a' ).removeClass( 'active-link' );
		$( this ).addClass( 'active-link' );
		var $comments = jQuery( e.target ).parents( 'h6' ).next( '.discussion-list' ); // eslint-disable-line vars-on-top
		var selector = $( e.target ).data( 'selector' ); // eslint-disable-line vars-on-top
		if ( 'all' === selector ) {
			$comments.children().show();
		} else if ( 'rejection-feedback' === selector ) {
			$comments.children().hide();
			$comments.children( '.rejection-feedback' ).show();
		} else {
			$comments.children().hide();
			$comments.children( '.comment-locale-' + selector ).show();
			$comments.children( '.comment-locale-' + selector ).next( 'ul' ).show();
		}
		return false;
	} );
	$( '.helper-translation-discussion' ).on( 'submit', '.comment-form', function( e ) {
		var $commentform = $( e.target );
		var formdata = {
			content: $commentform.find( 'textarea[name=comment]' ).val(),
			parent: $commentform.find( 'input[name=comment_parent]' ).val(),
			post: $commentform.attr( 'id' ).split( '-' )[ 1 ],
			meta: {
				translation_id: $commentform.find( 'input[name=translation_id]' ).val(),
				locale: $commentform.find( 'input[name=comment_locale]' ).val(),
				comment_topic: $commentform.find( 'select[name=comment_topic]' ).val(),
			},
		};
		e.preventDefault();
		e.stopImmediatePropagation();

		if ( ! formdata.meta.translation_id ) {
			formdata.meta.translation_id = 0;
		}

		if ( formdata.meta.locale ) {
			/**
			 * Set the locale to an empty string if option selected has value 'typo' or 'context'
			 * to force comment to be posted to the English discussion page
			 */
			if ( formdata.meta.comment_topic === 'typo' || formdata.meta.comment_topic === 'context' ) {
				formdata.meta.locale = '';
			}
		}

		$.ajax( {
			url: wpApiSettings.root + 'wp/v2/comments', // eslint-disable-line no-undef
			method: 'POST',
			beforeSend: function( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', wpApiSettings.nonce ); // eslint-disable-line no-undef
			},
			data: formdata,
		} ).done( function( response ) {
			if ( 'undefined' !== typeof ( response.data ) ) {
				// There's probably a better way, but response.data is only set for errors.
				// TODO: error handling.
			} else {
				$commentform.find( 'textarea[name=comment]' ).val( '' );
				$gp.translation_helpers.fetch( 'discussion' ); // eslint-disable-line no-undef
			}
		} );

		return false;
	} );
} );
