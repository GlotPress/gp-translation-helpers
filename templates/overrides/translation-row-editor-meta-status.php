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
										<label>Comment </label><textarea name="feedback_comment"></textarea>
									</div>
								</form>
							</div>
						</details>

						<?php if ( $translation->translation_status && ( $can_approve_translation || $can_reject_self ) ): ?>
							<div class="status-actions">
								<?php if ( $can_approve_translation ) : ?>
									<?php if ( 'current' !== $translation->translation_status ) : ?>
										<button class="button  is-primary approve" tabindex="-1" data-nonce="<?php echo esc_attr( wp_create_nonce( 'update-translation-status-current_' . $translation->id ) ); ?>"><strong>+</strong> <?php _e( 'Approve', 'glotpress' ); ?></button>
									<?php endif; ?>
									<?php if ( 'rejected' !== $translation->translation_status ) : ?>
										<button class="button reject" tabindex="-1" data-nonce="<?php echo esc_attr( wp_create_nonce( 'update-translation-status-rejected_' . $translation->id ) ); ?>"><strong>&minus;</strong> <?php _e( 'Reject', 'glotpress' ); ?></button>
									<?php endif; ?>
									<?php if ( 'fuzzy' !== $translation->translation_status ) : ?>
										<button class="button fuzzy" tabindex="-1" data-nonce="<?php echo esc_attr( wp_create_nonce( 'update-translation-status-fuzzy_' . $translation->id ) ); ?>"><strong>~</strong> <?php _e( 'Fuzzy', 'glotpress' ); ?></button>
									<?php endif; ?>
								<?php elseif ( $can_reject_self ): ?>
									<button class="button reject" tabindex="-1" data-nonce="<?php echo esc_attr( wp_create_nonce( 'update-translation-status-rejected_' . $translation->id ) ); ?>"><strong>&minus;</strong> <?php _e( 'Reject Suggestion', 'glotpress' ); ?></button>
									<button class="button fuzzy" tabindex="-1" data-nonce="<?php echo esc_attr( wp_create_nonce( 'update-translation-status-fuzzy_' . $translation->id ) ); ?>"><strong>~</strong> <?php _e( 'Fuzzy', 'glotpress' ); ?></button>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<dl>
							<dt><?php _e( 'Status:', 'glotpress' ); ?></dt>
							<dd>
								<?php echo display_status( $translation->translation_status ); ?>
							</dd>
						</dl>
