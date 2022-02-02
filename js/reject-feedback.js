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
            
            $('body').on('click' , '#gp_reject_btn', function(e){
                e.preventDefault();
                
                let reject_reason = [];
                $('input[name="reject_reason"]:checked').each(function() {
                    reject_reason.push(this.value);
                });
                
                reject_data = {};
                reject_data.locale_slug = $gp_reject_feedback_settings.locale_slug;
                reject_data.reason = reject_reason;
                reject_data.comment = $('textarea[name="reject_comment"]').val();
                reject_data.original_id = $gp.editor.current.original_id;
                reject_data.translation_id = $gp.editor.current.translation_id;
                
                const data = {
                    action: 'reject_with_feedback',
                    data: reject_data,
                    _ajax_nonce: $gp_reject_feedback_settings.nonce,
                };
                $.ajax( {
                    type: 'POST',
                    url: $gp_reject_feedback_settings.url,
                    data: data,
                    success: function( data ) {
                        $gp.notices.success( 'Translation Rejected!' );
                        $gp.editor.next();
                        $gp.editor.current.addClass( 'status-rejected' );
                        $('#TB_window, #TB_overlay').fadeOut();
                    },
                    error: function( xhr, msg ) {
                        button.prop( 'disabled', false );
                        msg = xhr.responseText ? 'Error: ' + xhr.responseText : 'Error setting the status!';
                        $gp.notices.error( msg );
                    }
                } );
            } );
		}
        
	);
    $gp.editor.hooks.set_status_rejected = function() {
        tb_show( 'Reject with Feedback', '#TB_inline?inlineId=reject-feedback-form' );
    }
}(jQuery, $gp)
);