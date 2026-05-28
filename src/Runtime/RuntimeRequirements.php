<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Runtime;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

final class RuntimeRequirements {

	private const REQUIRED_WORDPRESS_VERSION = '7.0';
	private const REQUIRED_PHP_VERSION = '8.2';

	private const REQUIRED_FUNCTIONS = [
		'add_action',
		'add_filter',
		'is_admin',
		'plugin_basename',
		'register_deactivation_hook',
		'wp_clear_scheduled_hook',
		'wp_next_scheduled',
		'wp_schedule_event',
	];

	private const REQUIRED_APPLICATION_PASSWORD_METHODS = [
		'delete_application_password',
		'get_user_application_passwords',
	];

	public static function unsupportedReason() :string {
		if ( version_compare( PHP_VERSION, self::REQUIRED_PHP_VERSION, '<' ) ) {
			return 'php';
		}

		if ( !self::hasSupportedWordPressVersion() ) {
			return 'wordpress';
		}

		foreach ( self::REQUIRED_FUNCTIONS as $function ) {
			if ( !function_exists( $function ) ) {
				return 'wordpress';
			}
		}

		if ( !class_exists( '\WP_Application_Passwords' ) ) {
			return 'wordpress';
		}

		foreach ( self::REQUIRED_APPLICATION_PASSWORD_METHODS as $method ) {
			if ( !method_exists( '\WP_Application_Passwords', $method ) ) {
				return 'wordpress';
			}
		}

		return '';
	}

	private static function hasSupportedWordPressVersion() :bool {
		if ( function_exists( 'wp_get_wp_version' ) ) {
			$wpVersion = wp_get_wp_version();
		}
		elseif ( function_exists( 'get_bloginfo' ) ) {
			$wpVersion = get_bloginfo( 'version' );
		}
		else {
			return false;
		}

		return is_string( $wpVersion )
			&& version_compare( $wpVersion, self::REQUIRED_WORDPRESS_VERSION, '>=' );
	}
}
