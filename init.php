<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Plugin;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\PluginIdentity;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Runtime\RuntimeRequirements;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

( static function () :void {
	$mdpsc_plugin_file = plugin_basename( __DIR__.'/mandate-app-security.php' );
	$mdpsc_autoload = __DIR__.'/vendor/autoload.php';

	if ( !\is_file( $mdpsc_autoload ) ) {
		$mdpsc_register_unsupported_notice = require __DIR__.'/unsupported.php';
		$mdpsc_register_unsupported_notice( $mdpsc_plugin_file, 'autoload' );
		return;
	}

	require_once $mdpsc_autoload;

	$mdpsc_unsupported_reason = RuntimeRequirements::unsupportedReason();
	if ( $mdpsc_unsupported_reason !== '' ) {
		$mdpsc_register_unsupported_notice = require __DIR__.'/unsupported.php';
		$mdpsc_register_unsupported_notice( $mdpsc_plugin_file, $mdpsc_unsupported_reason );
		return;
	}

	Plugin::boot( __DIR__.'/'.PluginIdentity::MAIN_FILE );
} )();
