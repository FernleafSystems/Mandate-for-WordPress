<?php declare( strict_types=1 );

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

\call_user_func( static function () :void {
	$mandate_update_checker = PucFactory::buildUpdateChecker(
		'https://github.com/FernleafSystems/Mandate-for-WordPress/',
		__DIR__.'/plugin.php',
		'mandate'
	);

	$mandate_update_checker->getVcsApi()->enableReleaseAssets( '/mandate-github-[^\/?&#]+\.zip($|[?&#])/i' );
} );
