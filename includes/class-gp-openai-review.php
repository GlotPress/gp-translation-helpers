<?php

class GP_OpenAI_Review {
	/**
	 * The OpenAI key.
	 *
	 * @var string
	 */
	const OPENAI_KEY = '';

	/**
	 * Get suggestions from OpenAI (ChatGPT).
	 *
	 * @param string       $original_singular The singular from the original string.
	 * @param string       $translation       The translation.
	 * @param string       $locale            The locale.
	 * @param \GP_Glossary $locale_glossary   The glossary for the locale.
	 *
	 * @return array
	 */
	public static function get_openai_review( $original_singular, $translation, $locale, $locale_glossary ): array {
		$openai_query   = '';
		$glossary_query = '';

		if ( empty( trim( self::OPENAI_KEY ) ) ) {
			return array();
		}
		$openai_temperature = 0;

		$glossary_entries = array();
		foreach ( $locale_glossary->get_entries() as $gp_glossary_entry ) {
			if ( strpos( strtolower( $original_singular ), strtolower( $gp_glossary_entry->term ) ) !== false ) {
				// Use the translation as key, because we could have multiple translations with the same term.
				$glossary_entries[ $gp_glossary_entry->translation ] = $gp_glossary_entry->term;
			}
		}
		if ( ! empty( $glossary_entries ) ) {
			$glossary_query = ' The following terms are translated as follows: ';
			foreach ( $glossary_entries as $glossary_translation => $term ) {
				$glossary_query .= '"' . $term . '" is translated as "' . $glossary_translation . '"';
				if ( array_key_last( $glossary_entries ) != $glossary_translation ) {
					$glossary_query .= ', ';
				}
			}
			$glossary_query .= '.';
		}

		$gp_locale     = GP_Locales::by_field( 'slug', $locale );
		$openai_query .= 'For the english text  "' . $original_singular . '", is "' . $translation . '" a correct translation in ' . $gp_locale->english_name . '?';

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $glossary_query,
			),
			array(
				'role'    => 'user',
				'content' => $openai_query,
			),
		);

		$openai_response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . self::OPENAI_KEY,
				),
				'body'    => wp_json_encode(
					array(
						'model'       => 'gpt-3.5-turbo',
						'max_tokens'  => 1000,
						'n'           => 1,
						'messages'    => $messages,
						'temperature' => $openai_temperature,
					)
				),
			)
		);
		if ( is_wp_error( $openai_response ) ) {
			return array();
		}
		$response_status = wp_remote_retrieve_response_code( $openai_response );
		if ( 200 !== $response_status ) {
			return array();
		}
		$output = json_decode( wp_remote_retrieve_body( $openai_response ), true );

		$message                      = $output['choices'][0]['message'];
		$response['openai']['review'] = trim( trim( $message['content'] ), '"' );
		$response['openai']['diff']   = '';

		return $response;
	}
}
