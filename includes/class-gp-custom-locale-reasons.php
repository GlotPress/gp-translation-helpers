<?php

class GP_Custom_Locale_Reasons extends GP_Route {
	/**
	 * Return the custom reasons set for the specified locale
	 *
	 * @since 0.0.2
	 *
	 * @param string $locale The locale for the custom reason
	 *
	 * @return array $locale_reasons[ $locale ] The custom reasons defined for the specified locale.
	 */
	public static function get_custom_reasons( $locale ) {
		// Add custom reasons here in this array in the format below ,
		// here's and example how to add a custom reason for the `yor` locale
		// Ensure the key for the custom reasons is not one of the following [ 'style', 'grammar', 'branding', 'glossary', 'punctuation', 'typo' ]
		// $locale_reasons = array(
		// 'yor' => array (
		// 'custom_style'       => array(
		// 'name'        => __( 'Custom Style Guide' ),
		// 'explanation' => __( 'The translation is not following the style guide. It will be interesting to provide a link to the style guide for your locale in the comment.' ),
		// ),
		// )
		// );
		$locale_reasons = array(
			'it' => array(
				'consistency'          => array(
					'name'        => 'Consistenza',
					'explanation' => 'Utilizzare una traduzione consistente',
				),
				'second_person'        => array(
					'name'        => '2a persona',
					'explanation' => 'Per i verbi utilizziamo la seconda persona singolare rivolgendoci direttamente all’utente',
				),
				'capitalize_titlecase' => array(
					'name'        => 'No maiuscole TitleCase',
					'explanation' => 'Verificare il corretto uso delle maiuscole in italiano, sono presenti maiuscole non necessarie/errate',
				),
				'double_space'         => array(
					'name'        => 'Spazio doppio',
					'explanation' => 'Sono presenti uno o più uno spazi doppi',
				),
				'beginning_space'      => array(
					'name'        => 'Spazio all’inizio o alla fine',
					'explanation' => 'Sono presenti/assenti spazi all’inizio o alla fine della traduzione diversamente dalla stringa originale',
				),
				'no_s_plural'          => array(
					'name'        => 'Niente s al plurale',
					'explanation' => 'In italiano non si riportano le s del plurale dei termini che rimangono invariati',
				),
			),
		);

		return isset( $locale_reasons[ $locale ] ) ? $locale_reasons[ $locale ] : array();
	}
}
