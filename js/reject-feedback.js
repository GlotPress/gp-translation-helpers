( function( $, $gp ) {
	// eslint-disable-next-line no-undef
	$( document ).ready(
		function() {
			var feedbackForm = '<details><summary>This is always shown</summary>' +
			'<div id="reject-feedback-form">' +
			'<form>' +
			'<h3 class="modal-reason-title">Reason</h3>' +
			'<div class="modal-item"><input type="checkbox" name="reject_reason" value="style" /><label>Style Guide </label></div>' +
			'<div class="modal-item"><input type="checkbox" name="reject_reason" value="grammar" /><label>Grammar </label></div>' +
			'<div class="modal-item"><input type="checkbox" name="reject_reason" value="branding" /><label>Branding </label></div>' +
			'<div class="modal-item"><input type="checkbox" name="reject_reason" value="glossary" /><label>Glossary </label></div>' +
			'<div class="modal-item"><input type="checkbox" name="reject_reason" value="punctuation" /><label>Punctuation </label></div>' +
			'<div class="modal-item"><input type="checkbox" name="reject_reason" value="typo" /><label>Typo </label></div>' +
			'<div class="modal-comment">' +
				'<label>Comment </label>' +
				'<textarea name="reject_comment"></textarea>' +
			'</div>' +
			'</form>' +
			'</div>' +
			'</details>';

			$( '.meta' ).prepend( feedbackForm );
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

		$( 'input[name="reject_reason"]:checked' ).each(
			function() {
				rejectReason.push( this.value );
			}
		);

		comment = $( 'textarea[name="reject_comment"]' ).val();

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
				$( 'input[name="reject_reason"]' ).prop( 'checked', false );
				$( 'textarea[name="reject_comment"]' ).val( '' );
			}
		);
	}
// eslint-disable-next-line no-undef
}( jQuery, $gp )
);
