<?php if ( ! $can_approve_translation || ! $translation->translation_status ) {
	return;
}
	$current_set_slug                = 'default';
	$locale_glossary_translation_set = GP::$translation_set->by_project_id_slug_and_locale( 0, $current_set_slug, $locale_slug );
	$locale_glossary                 = GP::$glossary->by_set_id( $locale_glossary_translation_set->id );

	$openai_response = GP_OpenAI_Review::get_openai_review( $translation->singular, $translation->translations[0], $locale_slug, $locale_glossary );
	// var_dump( $openai_response );
?>
<div>
	<?php if ( ! empty( $openai_response['openai']['review'] ) ) : ?>
		<div class="openai-review">
			<h4><?php esc_html_e( 'Auto-review by ChatGPT', 'glotpress' ); ?></h4>
			<?php echo esc_html( $openai_response['openai']['review'] ); ?>
		</div>
	<?php endif; ?>
</div>
<details open>
	<summary class="feedback-summary"><?php esc_html_e( 'Give feedback', 'glotpress' ); ?></summary>
	<div id="feedback-form">
		<form>
			<h3 class="feedback-reason-title"><?php esc_html_e( 'Type (Optional)', 'glotpress' ); ?></h3>
			<ul class="feedback-reason-list">
			<?php
				$comment_reasons = Helper_Translation_Discussion::get_comment_reasons( $locale_slug );
			foreach ( $comment_reasons as $key => $reason ) :
				?>
					<li>
						<label><input type="checkbox" name="feedback_reason" value="<?php echo esc_attr( $key ); ?>" /><span class="gp-reason-text"><?php echo esc_html( $reason['name'] ); ?></span><span class="tooltip dashicons dashicons-info" title="<?php echo esc_attr( $reason['explanation'] ); ?>"></span></label>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="feedback-comment">
				<label for="feedback_comment"><?php esc_html_e( 'Comment (Optional)', 'glotpress' ); ?>
				</label>
				<textarea name="feedback_comment"></textarea>

				<label class="note">Please note that all feedback is visible to the public.</label>
			</div>
		</form>
	</div>
</details>
