<?php
/**
 * Helper that shows the history for a string in the current locale
 *
 * @package gp-translation-helpers
 * @since 0.0.1
 */
class Helper_History extends GP_Translation_Helper {

	/**
	 * Helper priority.
	 *
	 * @since 0.0.1
	 * @var int
	 */
	public $priority = 2;

	/**
	 * Helper title.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	public $title = 'History';

	/**
	 * Indicates whether the helper loads asynchronous content or not.
	 *
	 * @since 0.0.1
	 * @var bool
	 */
	public $has_async_content = true;

	/**
	 * Indicates whether the helper should be active or not.
	 *
	 * @since 0.0.2
	 *
	 * @return bool
	 */
	public function activate(): bool {
		if ( ! $this->data['translation_set_slug'] || ! isset( $this->data['translation_set_slug'] ) || ! isset( $this->data['locale_slug'] ) ) {
			// Deactivate when translation set is available.
			return false;
		}

		return true;
	}

	/**
	 * Gets asynchronously the translation history of the string.
	 *
	 * @since 0.0.1
	 *
	 * @return mixed|void
	 */
	public function get_async_content() {
		$translation_set = GP::$translation_set->by_project_id_slug_and_locale( $this->data['project_id'], $this->data['translation_set_slug'], $this->data['locale_slug'] );

		if ( ! $translation_set ) {
			return;
		}

		$translations = GP::$translation->find_many_no_map(
			array(
				'translation_set_id' => $translation_set->id,
				'original_id'        => $this->data['original_id'],
			)
		);

		usort(
			$translations,
			function ( $t1, $t2 ) {
				$cmp_prop_t1 = $t1->date_modified ?? $t1->date_added;
				$cmp_prop_t2 = $t2->date_modified ?? $t2->date_added;
				return $cmp_prop_t1 < $cmp_prop_t2;
			}
		);

		$this->set_count( $translations );

		return $translations;
	}

	/**
	 * Gets the items that will be rendered by the helper.
	 *
	 * @since 0.0.1
	 *
	 * @param array $translations   Translation history.
	 *
	 * @return string
	 */
	public function async_output_callback( array $translations ): string {
		if ( $translations ) {
			$output  = '<table id="translation-history-table">';
			$output .= '<thead>';
			$output .= '<tr><th>Date</th><th>Translation</th><th>Added by</th><th>Last modified by</th>';
			$output .= '</thead>';

			foreach ( $translations as $key => $translation ) {
				$date_and_time = is_null( $translation->date_modified ) ? $translation->date_added : $translation->date_modified;
				$date_and_time = explode( ' ', $date_and_time );

				$user               = get_userdata( $translation->user_id );
				$user_last_modified = get_userdata( $translation->user_id_last_modified );

				if ( ( null === $translation->translation_1 ) && ( null === $translation->translation_2 ) &&
					 ( null === $translation->translation_3 ) && ( null === $translation->translation_4 ) &&
					 ( null === $translation->translation_5 ) ) {
						$output_translation = $translation->translation_0;
				} else {
					$output_translation = '<ul>';
					for ( $i = 0; $i <= 5; $i ++ ) {
						if ( null !== $translation->{'translation_' . $i} ) {
							$output_translation .= sprintf( '<li>%s</li>', esc_translation( $translation->{'translation_' . $i} ) );
						}
					}
					$output_translation .= '</ul>';
				}

				$output .= sprintf(
					'<tr class="preview status-%1$s"><td title="%2$s">%3$s</td><td>%4$s</td><td>%5$s</td><td>%6$s</td></tr>',
					esc_attr( $translation->status ),
					esc_attr( $translation->date_modified ?? $translation->date_added ),
					esc_html( $date_and_time[0] ),
					$output_translation,
					$user ? esc_html( $user->user_login ) : '&mdash;',
					$user_last_modified ? esc_html( $user_last_modified->user_login ) : '&mdash;'
				);
			}
		}
		return $output;
	}

	/**
	 * Gets the content/string to return when a helper has no results.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function empty_content(): string {
		return esc_html__( 'No translation history for this string.' );
	}
}
