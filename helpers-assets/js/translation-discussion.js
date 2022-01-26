jQuery( function( $ ) {
	$('.helper-translation-discussion').on( 'click', '.comments-selector a', function( e ){
		e.preventDefault();
		$('.comments-selector a').removeClass('active-link');
		$(this).addClass('active-link');
		$localeComments = $('li:not(.hidden-comment)');
		$otherComments = $('li.hidden-comment');
		var selector = $(e.target).data('selector');
		
		if ( 'all' === selector  ) {
			$otherComments.show();
			$localeComments.hide();
		} else {
			$otherComments.hide();
			$localeComments.show();
		}
		return false;
	} );
	$('.helper-translation-discussion').on( 'submit', '.comment-form', function( e ){
		e.preventDefault();
		e.stopImmediatePropagation();
		var $commentform = $( e.target );
		var formdata = {
			content: $commentform.find('textarea[name=comment]').val(),
			parent: $commentform.find('input[name=comment_parent]').val(),
			post: $commentform.attr('id').split( '-' )[ 1 ],
			meta: {
				translation_id : $commentform.find('input[name=translation_id]').val(),
				locale         : $commentform.find('input[name=comment_locale]').val(),
				comment_topic  : $commentform.find('select[name=comment_topic]').val(),
			}
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
			url: wpApiSettings.root + 'wp/v2/comments',
			method: 'POST',
			beforeSend: function ( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', wpApiSettings.nonce );
			},
			data: formdata
		}).done( function( response ){
			if ( 'undefined' !== typeof ( response.data ) ) {
				// There's probably a better way, but response.data is only set for errors.
				// TODO: error handling.
			} else {
				$commentform.find('textarea[name=comment]').val('');
				$gp.translation_helpers.fetch( 'discussion' );
			}
		} );

		return false;
	});
});
