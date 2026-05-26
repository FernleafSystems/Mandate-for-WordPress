<?php declare( strict_types=1 );

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
use FernleafSystems\Wordpress\Plugin\Mandate\PluginIdentity;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

\call_user_func( static function () :void {
	$mandate_update_checker = PucFactory::buildUpdateChecker(
		'https://github.com/FernleafSystems/Mandate-for-WordPress/',
		__DIR__.'/'.PluginIdentity::MAIN_FILE,
		PluginIdentity::SLUG
	);

	$mandate_update_checker->getVcsApi()->enableReleaseAssets( '/'.PluginIdentity::GITHUB_ASSET_PREFIX.'-[^\/?&#]+\.zip($|[?&#])/i' );
} );
