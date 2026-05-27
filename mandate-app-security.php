<?php
/*
 * Plugin Name: Mandate App Security
 * Plugin URI: https://wpmandate.com
 * Description: Scoping AI access for WordPress by controlling Application Password capabilities.
 * Version: 0.4.1
 * Requires at least: 7.0
 * Requires PHP: 8.2
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mandate-app-security
 */

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

global $mandate_app_security_plugin_file, $mandate_app_security_unsupported_reason;
$mandate_app_security_plugin_file = plugin_basename( __FILE__ );

$mandate_app_security_unsupported_reason = '';
if ( \version_compare( PHP_VERSION, '8.2', '<' ) ) {
	$mandate_app_security_unsupported_reason = 'php';
}
elseif ( !\function_exists( 'wp_get_wp_version' ) || \version_compare( wp_get_wp_version(), '7.0', '<' ) ) {
	$mandate_app_security_unsupported_reason = 'wordpress';
}

if ( empty( $mandate_app_security_unsupported_reason ) ) {
	\call_user_func( function () {
		$mandate_app_security_init = __DIR__.'/init.php';
		if ( is_file( $mandate_app_security_init ) ) {
			require_once $mandate_app_security_init;
		}
	} );
}
else {
	include_once __DIR__.'/unsupported.php';
}
