<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Mandate\Plugin;
use FernleafSystems\Wordpress\Plugin\Mandate\PluginIdentity;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

\call_user_func( function () {
	$mandate_app_security_autoload = __DIR__.'/vendor/autoload.php';
	if ( \is_file( $mandate_app_security_autoload ) ) {
		require_once $mandate_app_security_autoload;
		Plugin::boot( __DIR__.'/'.PluginIdentity::MAIN_FILE );
	}
	else {
		global $mandate_app_security_unsupported_reason;
		$mandate_app_security_unsupported_reason = 'autoload';
		include_once __DIR__.'/unsupported.php';
	}
} );
