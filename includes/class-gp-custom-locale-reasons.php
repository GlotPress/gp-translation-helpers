<?php

class GP_Custom_Locale_Reasons extends GP_Route {
	public static function get_custom_reasons( $locale ) {
		// Add custom reasons here in this array
		$locale_reasons = array();

		return isset( $locale_reasons[ $locale ] ) ? $locale_reasons[ $locale ] : array();
	}
}
