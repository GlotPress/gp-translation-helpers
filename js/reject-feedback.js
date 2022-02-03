(function( $, $gp ) {
	$( document ).ready(
		function($) {

			let reject_feedback_form =
			'<div id="reject-feedback-form" style="display:none;">' +
			'<form>' +
			'<h3>Reason</h3>' +
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
			'<button class="modal-btn" id="gp_reject_btn">Reject</button>' +
			'</form>' +
			'</div>';

			$( "body" ).append( reject_feedback_form );

			function postComment( reject_reason, comment ){
				reject_data                = {};
				reject_data.locale_slug    = $gp_reject_feedback_settings.locale_slug;
				reject_data.reason         = reject_reason;
				reject_data.comment        = comment;
				reject_data.original_id    = $gp.editor.current.original_id;
				reject_data.translation_id = $gp.editor.current.translation_id;

				const data = {
					action: 'reject_with_feedback',
					data: reject_data,
					_ajax_nonce: $gp_reject_feedback_settings.nonce,
				};
				$.ajax(
					{
						type: 'POST',
						url: $gp_reject_feedback_settings.url,
						data: data}
				).done(
					function( response ){
						// TODO: Handling response
					}
				);
			}

			$( 'body' ).on(
				'click' ,
				'#gp_reject_btn',
				function(e){
					e.preventDefault();

					let reject_reason = [];
					$( 'input[name="reject_reason"]:checked' ).each(
						function() {
							reject_reason.push( this.value );
						}
					);

					let comment = $( 'textarea[name="reject_comment"]' ).val();

					data = {
						translation_id: $gp.editor.current.translation_id,
						status: 'rejected',
						_gp_route_nonce: $( 'button.reject' ).data( 'nonce' ),
					};

					$.ajax(
						{
							type: 'POST',
							url: $gp_editor_options.set_status_url,
							data: data,
							success: function( response ) {
								if ( reject_reason || comment ) {
									postComment( reject_reason, comment );
								}
								$gp.notices.success( 'Translation Rejected!' );
								$gp.editor.replace_current( response );
								$gp.editor.next();
								$( '#TB_window, #TB_overlay' ).fadeOut();

							},
							error: function( xhr, msg ) {
								msg = xhr.responseText ? 'Error: ' + xhr.responseText : 'Error setting the status!';
								$gp.notices.error( msg );
							}
						}
					);
				}
			);
		}
	);
	$gp.editor.hooks.set_status_rejected = function() {
		tb_show( 'Reject with Feedback', '#TB_inline?inlineId=reject-feedback-form' );
	}
}(jQuery, $gp)
);