<?php
/**
 * @package gp-translation-helpers
 */

/**
 * Plugin name:     GP Translation Helpers
 * Plugin URI:      https://github.com/GlotPress/gp-translation-helpers
 * Description:     GlotPress plugin to discuss the strings that are being translated in GlotPress.
 * Version:         0.0.2
 * Requires PHP:    7.2
 * Author:          the GlotPress team
 * Author URI:      https://glotpress.blog
 * License:         GPLv2 or later
 * Text Domain:     gp-translation-helpers
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

require_once __DIR__ . '/includes/class-gp-route-translation-helpers.php';
require_once __DIR__ . '/includes/class-gp-translation-helpers.php';

add_action( 'gp_init', array( 'GP_Translation_Helpers', 'init' ) );
add_action(
	'comment_post',
	function( $comment_id ) {
		if ( gp_post( 'translation_id' ) ) {
			$translation_id = sanitize_text_field( gp_post( 'translation_id' ) );
			add_comment_meta( $comment_id, 'translation_id', $translation_id );
		}
		if ( gp_post( 'comment_locale' ) ) {
			$comment_locale = sanitize_text_field( gp_post( 'comment_locale' ) );
			add_comment_meta( $comment_id, 'locale', $comment_locale );
		}
	}
);
