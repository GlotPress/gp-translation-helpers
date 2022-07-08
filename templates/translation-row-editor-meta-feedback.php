<?php if ( ! $can_approve_translation || ! $translation->translation_status ) {
	return;
}  ?>
<details>
	<summary class="feedback-summary">Give feedback</summary>
	<div id="feedback-form">
		<form>
			<h3 class="feedback-reason-title">Reason</h3>
			<ul class="feedback-reason-list">
			<?php
				$reject_reasons = Helper_Translation_Discussion::get_reject_reasons();
			foreach ( $reject_reasons as $key => $reason ) :
				?>
					<li><label><input type="checkbox" name="feedback_reason" value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $reason ); ?></label></li>
			<?php endforeach; ?>
			</ul>
			<div class="feedback-comment">
				<label>Comment </label>
				<textarea name="feedback_comment"></textarea>
			</div>
		</form>
	</div>
</details>
