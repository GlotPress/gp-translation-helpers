<table id="translations" class="translations clear">
		<thead>

		</thead>
		​
		<tbody>
			<tr class="preview untranslated priority-normal no-warnings no-translations" id="preview-12561869" row="12561869" style="display: none;">
				<th scope="row" class="checkbox"><input type="checkbox" name="selected-row[]"></th>
				<td class="priority" title="Priority: normal">
				</td>
				<td class="original">
					<span class="original-text"><?php echo esc_html( $original->singular ); ?></span>

					<div class="original-tags">
					</div>
				</td>
				<td class="translation foreign-text">
					<span class="missing">Double-click to add</span> </td>
				<td class="actions">
					<a href="#" class="action edit">Details</a>
				</td>
			</tr>
			<tr class="editor untranslated priority-normal no-warnings no-translations" id="editor-12561869" row="12561869" style="display: table-row;">
				<td colspan="5">
					<div class="editor-panel">
						<div class="editor-panel__left">
							<div class="panel-header">
								<h3>Original <span class="panel-header__bubble">untranslated</span></h3>
							</div>
							<div class="panel-content">
								<div class="source-string strings">
									<div class="source-string__singular">
										<span class="original"><?php echo esc_html( $original->singular ); ?></span>
										<span aria-hidden="true" class="original-raw"><?php echo esc_html( $original->singular ); ?></span>
									</div>
								</div>
								​
								<div class="source-details">
									<details class="source-details__references" close="">
										<summary>Comments all
										<?php foreach ( $locales_with_comments as $locale_with_comments ) : ?>
											<a class="<?php echo esc_attr( $locale_with_comments == $locale_slug ? 'active-locale-link' : '' ); ?>" href="<?php echo esc_attr( $args['original_permalink'] . $locale_with_comments . '/default' ); ?>">
												| <?php echo $locale_with_comments; ?>
											</a>
										<?php endforeach; ?>
										</summary>

										<?php gp_tmpl_load( 'comment-section', get_defined_vars(), dirname( __FILE__ ) ); ?>

									</details>
								</div>
								<div class="suggestions-wrapper">
									<details class="suggestions__other-languages initialized" data-nonce="b1ee0a8267" open="">
										<summary>All Languages</summary>
										<?php if ( $translations_by_locale ) : ?>
											<ul class="suggestions-list">
											<?php foreach ( $translations_by_locale as $locale => $translation ) : ?>
												<li>
													<div class="translation-suggestion with-tooltip" tabindex="0" role="button" aria-pressed="false" aria-label="Copy translation">
														<span class="translation-suggestion__translation">
															<?php echo strtoupper( $locale ) . ' - ' . esc_html( $translation ); ?>
														</span>
													</div>
												</li>
											<?php endforeach; ?>	
											</ul>
										<?php else : ?>
											<p class="no-suggestions">No suggestions.</p>
										<?php endif; ?>
									</details>
								</div>
								​ ​

							</div>
						</div>
						​
						<div class="editor-panel__right">
							<div class="panel-header">
								<h3>Meta</h3>
							</div>
							<div class="panel-content">
								<div class="meta">
									​

									<dl>
										<dt>Status:</dt>
										<dd>
											untranslated </dd>
									</dl>
									​

									<dl>
										<dt>Priority of the original:</dt>
										<dd>normal</dd>
									</dl>
									<div class="source-details">
										<details class="source-details__references">
											<summary>References</summary>
											<ul>
												<li><a target="_blank" href="https://plugins.trac.wordpress.org/browser/friends/trunk/templates/frontend/messages/message-form.php#L36">templates/frontend/messages/message-form.php:36</a></li>
											</ul>
										</details>
									</div>
								</div>
								​
							</div>
						</div>
					</div>
				</td>
			</tr>
			​ ​ ​ ​ ​ ​ ​ ​ ​ ​ ​ ​ ​ ​ ​ ​ ​ ​ ​ ​ ​ ​ ​ ​ ​ ​
			<tr class="editor untranslated priority-normal no-warnings no-translations" id="editor-12561837" row="12561837">
				<td colspan="5">
					<div class="editor-panel">
						<div class="editor-panel__left">
							<div class="panel-header">
								<h3>Original <span class="panel-header__bubble">untranslated</span></h3>
							</div>
							<div class="panel-content">
								<div class="source-string strings">
									<div class="source-string__singular">
										<span class="original">Start writing or type / to choose a block</span>
										<span aria-hidden="true" class="original-raw">Start writing or type / to choose a block</span>
									</div>
								</div>
								​
								<div class="source-details">
									<details class="source-details__references">
										<summary>References</summary>
										<ul>
											<li><a target="_blank" href="https://plugins.trac.wordpress.org/browser/friends/trunk/libs/gutenberg-everywhere/classes/iso-gutenberg.php#L236">libs/gutenberg-everywhere/classes/iso-gutenberg.php:236</a></li>
											<li><a target="_blank" href="https://plugins.trac.wordpress.org/browser/friends/trunk/libs/gutenberg-everywhere/build/index.js#L8">libs/gutenberg-everywhere/build/index.js:8</a></li>
										</ul>
									</details>
								</div>
								​
								<div class="translation-wrapper">

									<div class="textareas active" data-plural-index="0">
										<textarea placeholder="Enter translation here" class="foreign-text" name="translation[12561837][]" id="translation_12561837_0"></textarea>
									</div>

									<div class="translation-actions">
										<div class="translation-actions__primary">
											<button class="translation-actions__save with-tooltip" type="button" aria-label="Save and approve translation" data-nonce="c4eba2cfbc">
												Save									</button>
										</div>
										<div class="translation-actions__secondary">
											<button type="button" class="translation-actions__copy with-tooltip" aria-label="Copy original">
												<span class="screen-reader-text">Copy</span><span aria-hidden="true" class="dashicons dashicons-admin-page"></span>
											</button>
											<button type="button" class="translation-actions__ltr with-tooltip" aria-label="Switch to LTR">
												<span class="screen-reader-text">LTR</span><span aria-hidden="true" class="dashicons dashicons-editor-ltr"></span>
											</button>
											<button type="button" class="translation-actions__rtl with-tooltip" aria-label="Switch to RTL">
												<span class="screen-reader-text">RTL</span><span aria-hidden="true" class="dashicons dashicons-editor-rtl"></span>
											</button>
											<button type="button" class="translation-actions__help with-tooltip" aria-label="Show help">
												<span class="screen-reader-text">Help</span><span aria-hidden="true" class="dashicons dashicons-editor-help"></span>
											</button>
										</div>
									</div>
								</div>
								​
								<div class="suggestions-wrapper">
									<details open="" class="suggestions__translation-memory" data-nonce="7a3fc4fb3d">
										<summary>Suggestions from Translation Memory</summary>
										<p class="suggestions__loading-indicator">Loading <span aria-hidden="true" class="suggestions__loading-indicator__icon"><span></span><span></span><span></span></span>
										</p>
									</details>
									​
									<details class="suggestions__other-languages" data-nonce="f84f927c1a">
										<summary>Other Languages</summary>
										<p class="suggestions__loading-indicator">Loading <span aria-hidden="true" class="suggestions__loading-indicator__icon"><span></span><span></span><span></span></span>
										</p>
									</details>
								</div>
							</div>
						</div>
						​
						<div class="editor-panel__right">
							<div class="panel-header">
								<h3>Meta</h3>
							</div>
							<div class="panel-content">
								<div class="meta">
									​

									<dl>
										<dt>Status:</dt>
										<dd>
											untranslated </dd>
									</dl>
									​

									<dl>
										<dt>Priority of the original:</dt>
										<dd>normal</dd>
									</dl>
								</div>
								​
							</div>
						</div>
					</div>
				</td>
			</tr>
		</tbody>
	</table>