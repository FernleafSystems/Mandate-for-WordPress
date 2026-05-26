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

	$mandate_release_asset_prefixes = \array_map(
		static fn( string $assetPrefix ) :string => \preg_quote( $assetPrefix, '/' ),
		PluginIdentity::GITHUB_ASSET_PREFIXES
	);

	$mandate_update_checker->getVcsApi()->enableReleaseAssets(
		'/(?:'.\implode( '|', $mandate_release_asset_prefixes ).')-[^\/?&#]+\.zip($|[?&#])/i'
	);
} );
