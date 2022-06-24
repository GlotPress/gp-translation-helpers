<?php
/**
 * Bootstraps unit-tests.
 *
 * @package GlotPress
 * @subpackage Tests
 */

if ( ! defined( 'GP_TESTS_DIR' ) ) {
	define( 'GP_TESTS_DIR', __DIR__ );
}

if ( ! defined( 'GP_DIR_TESTDATA' ) ) {
	define( 'GP_DIR_TESTDATA', GP_TESTS_DIR . '/data' );
}

if ( ! defined( 'GP_TESTS_PERMALINK_STRUCTURE' ) ) {
	define( 'GP_TESTS_PERMALINK_STRUCTURE', '/%postname%' );
}

if ( ! defined( 'GP_TESTS_PERMALINK_STRUCTURE_WITH_TRAILING_SLASH' ) ) {
	define( 'GP_TESTS_PERMALINK_STRUCTURE_WITH_TRAILING_SLASH', '/%postname%/' );
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' );
}
$_tests_dir .= '/wordpress-tests-lib';

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

if ( getenv( 'GLOTPRESS_PATH' ) ) {
	define( 'EXTERNAL_GP_PATH', getenv( 'GLOTPRESS_PATH' ) );
} else {
	define( 'EXTERNAL_GP_PATH', dirname( __DIR__, 3 ) . '/glotpress/' );
}

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require EXTERNAL_GP_PATH . 'glotpress.php';
	require dirname( __DIR__, 2 ) . '/gp-translation-helpers.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

global $wp_tests_options;
$wp_tests_options['permalink_structure'] = GP_TESTS_PERMALINK_STRUCTURE;

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

require_once GP_TESTS_DIR . '/lib/testcase.php';
require_once GP_TESTS_DIR . '/lib/testcase-route.php';
require_once GP_TESTS_DIR . '/lib/testcase-request.php';

/**
 * Installs GlotPress tables.
 */
function _install_glotpress() {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	require_once EXTERNAL_GP_PATH . '/gp-includes/schema.php';
	require_once EXTERNAL_GP_PATH . '/gp-includes/install-upgrade.php';
	gp_upgrade_db();
}
_install_glotpress();
