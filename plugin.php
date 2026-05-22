<?php
/*
 * Plugin Name: Application Password Scoper
 * Plugin URI: https://github.com/FernleafSystems/WP_Plugin-Application_Password_Scoper
 * Description: Restrict WordPress Application Passwords to a saved capability allowlist.
 * Version: 0.1.0
 * Author: FernleafSystems
 * Requires at least: 7.0
 * Requires PHP: 8.2
 * Text Domain: application-password-scoper
 */

if ( !\defined( 'ABSPATH' ) ) {
	exit;
}

global $application_password_scoper_plugin_file, $application_password_scoper_unsupported_reason;
$application_password_scoper_plugin_file = plugin_basename( __FILE__ );

$application_password_scoper_unsupported_reason = '';
if ( \version_compare( PHP_VERSION, '8.2', '<' ) ) {
	$application_password_scoper_unsupported_reason = 'php';
}
elseif ( !\function_exists( 'wp_get_wp_version' ) || \version_compare( wp_get_wp_version(), '7.0', '<' ) ) {
	$application_password_scoper_unsupported_reason = 'wordpress';
}

if ( empty( $application_password_scoper_unsupported_reason ) ) {
	\call_user_func( function () {
		$application_password_scoper_init = __DIR__.'/init.php';
		if ( is_file( $application_password_scoper_init ) ) {
			require_once $application_password_scoper_init;
		}
	} );
}
else {
	include_once __DIR__.'/unsupported.php';
}
