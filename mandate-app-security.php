<?php
/*
 * Plugin Name: Mandate App Security
 * Plugin URI: https://wpmandate.com
 * Description: Scoping AI access for WordPress by controlling Application Password capabilities.
 * Version: 0.4.2
 * Requires at least: 7.0
 * Requires PHP: 8.2
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mandate-app-security
 */

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

( static function () :void {
	$mdpsc_plugin_file = plugin_basename( __FILE__ );

	if ( \version_compare( PHP_VERSION, '8.2', '<' ) ) {
		$mdpsc_register_unsupported_notice = require __DIR__.'/unsupported.php';
		$mdpsc_register_unsupported_notice( $mdpsc_plugin_file, 'php' );
		return;
	}

	require_once __DIR__.'/init.php';
} )();
