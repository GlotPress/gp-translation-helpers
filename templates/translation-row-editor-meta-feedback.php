<?php if ( ! $can_approve_translation || ! $translation->translation_status ) {
	return;
}  ?>
<details>
	<summary class="feedback-summary">Give feedback</summary>
	<div id="feedback-form">
		<form>
			<h3 class="feedback-reason-title">Reason</h3>
			<ul class="feedback-reason-list">
				<li><label><input type="checkbox" name="feedback_reason" value="style">Style Guide</label></li>
				<li><label><input type="checkbox" name="feedback_reason" value="grammar">Grammar</label></li>
				<li><label><input type="checkbox" name="feedback_reason" value="branding">Branding</label></li>
				<li><label><input type="checkbox" name="feedback_reason" value="glossary">Glossary</label></li>
				<li><label><input type="checkbox" name="feedback_reason" value="punctuation">Punctuation</label></li>
				<li><label><input type="checkbox" name="feedback_reason" value="typo">Typo</label></li>
			</ul>
			<div class="feedback-comment">
				<label>Comment </label>
				<textarea name="feedback_comment"></textarea>
			</div>
		</form>
	</div>
</details>
