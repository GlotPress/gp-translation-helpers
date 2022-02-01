(function( $, $gp ) {
	$( document ).ready(
		function($) {
			let reject_feedback_form =
			'<div id="reject-feedback-form" style="display:none;">' +
			'<form>' +
			'<h3>Reason</h3>' +
			'<div class="modal-item"><input type="checkbox" name="" /><label>Style Guide </label></div>' +
			'<div class="modal-item"><input type="checkbox" name="" /><label>Grammar </label></div>' +
			'<div class="modal-item"><input type="checkbox" name="" /><label>Branding </label></div>' +
			'<div class="modal-item"><input type="checkbox" name="" /><label>Glossary </label></div>' +
			'<div class="modal-item"><input type="checkbox" name="" /><label>Punctuation </label></div>' +
			'<div class="modal-item"><input type="checkbox" name="" /><label>Typo </label></div>' +
			'<div class="modal-comment">' +
				'<label>Comment </label>' +
				'<textarea></textarea>' +
			'</div>' +
			'<button class="modal-btn">Reject</button>' +
			'</form>' +
			'</div>';

			$( "body" ).append( reject_feedback_form );
		}
	);

	$gp.editor.hooks.set_status_rejected = function() {
		tb_show( 'Reject with Feedback', '#TB_inline?inlineId=reject-feedback-form' );
	}

}(jQuery, $gp)
);