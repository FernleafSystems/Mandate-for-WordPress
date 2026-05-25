<?php

declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate;

if ( !defined( 'ABSPATH' ) && PHP_SAPI !== 'cli' ) {
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
}
