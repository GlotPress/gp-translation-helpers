( function( $, $gp ) {
	// eslint-disable-next-line no-undef
	$( document ).ready(
		function() {
			var feedbackForm = '<details><summary class="feedback-summary">Give feedback</summary>' +
			'<div id="feedback-form">' +
			'<form>' +
			'<h3 class="feedback-reason-title">Reason</h3>' +
			'<ul class="feedback-reason-list">' +
			'<li><label><input type="checkbox" name="feedback_reason" value="style" />Style Guide</label></li>' +
			'<li><label><input type="checkbox" name="feedback_reason" value="grammar" />Grammar</label></li>' +
			'<li><label><input type="checkbox" name="feedback_reason" value="branding" />Branding</label></li>' +
			'<li><label><input type="checkbox" name="feedback_reason" value="glossary" />Glossary</label></li>' +
			'<li><label><input type="checkbox" name="feedback_reason" value="punctuation" />Punctuation</label></li>' +
			'<li><label><input type="checkbox" name="feedback_reason" value="typo" />Typo</label></li></ul>' +
			'<div class="feedback-comment">' +
				'<label>Comment </label>' +
				'<textarea name="feedback_comment"></textarea>' +
			'</div>' +
			'</form>' +
			'</div>' +
			'</details>';

			// Remove click event added to <summary> by wporg-gp-customizations plugin
			$( $gp.editor.table ).off( 'click', 'summary' );

			$( 'button.reject' ).closest( 'dl,div.status-actions' ).prepend( feedbackForm );
		}
	);

	$gp.editor.hooks.set_status_rejected = function() {
		var button = $( this );
		var status = 'rejected';
		rejectWithFeedback( button, status );
	};

	function rejectWithFeedback( button, status ) {
		var comment = '';
		var rejectReason = [];
		var rejectData = {};
		var data = {};

		$( 'input[name="feedback_reason"]:checked' ).each(
			function() {
				rejectReason.push( this.value );
			}
		);

		comment = button.closest( '.meta' ).find( 'textarea[name="feedback_comment"]' ).val();

		// eslint-disable-next-line no-undef
		rejectData.locale_slug = $gp_reject_feedback_settings.locale_slug;
		rejectData.reason = rejectReason;
		rejectData.comment = comment;
		rejectData.original_id = $gp.editor.current.original_id;
		rejectData.translation_id = $gp.editor.current.translation_id;

		data = {
			action: 'reject_with_feedback',
			data: rejectData,
			// eslint-disable-next-line no-undef
			_ajax_nonce: $gp_reject_feedback_settings.nonce,
		};

		$.ajax(
			{
				type: 'POST',
				// eslint-disable-next-line no-undef
				url: $gp_reject_feedback_settings.url,
				data: data,
			}
		).done(
			function() {
				$gp.editor.set_status( button, status );
				$( 'input[name="feedback_reason"]' ).prop( 'checked', false );
				$( 'textarea[name="feedback_comment"]' ).val( '' );
			}
		);
	}
// eslint-disable-next-line no-undef
}( jQuery, $gp )
);
