<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Plugin;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

\call_user_func( function () {
	$application_password_scoper_autoload = __DIR__.'/vendor/autoload.php';
	if ( \is_file( $application_password_scoper_autoload ) ) {
		require_once $application_password_scoper_autoload;
		Plugin::boot( __DIR__.'/plugin.php' );
	}
	else {
		global $application_password_scoper_unsupported_reason;
		$application_password_scoper_unsupported_reason = 'autoload';
		include_once __DIR__.'/unsupported.php';
	}
} );
