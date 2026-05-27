<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

final class PluginIdentity {

	public const NAME = 'Mandate App Security';
	public const SLUG = 'mandate-app-security';
	public const TEXT_DOMAIN = 'mandate-app-security';
	public const MAIN_FILE = 'mandate-app-security.php';
	public const CONTRIBUTOR = 'paultgoodchild';
	public const PACKAGE_ROOT = self::SLUG.'/';
	public const GITHUB_ASSET_PREFIX = self::SLUG.'-github';
	public const LEGACY_GITHUB_ASSET_PREFIX = 'mandate-github';
	public const GITHUB_ASSET_PREFIXES = [
		self::GITHUB_ASSET_PREFIX,
		self::LEGACY_GITHUB_ASSET_PREFIX,
	];
}
