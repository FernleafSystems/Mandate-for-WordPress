<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\MandateAppSecurity;

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
	public const RUNTIME_PREFIX = 'mdpsc';
	public const RUNTIME_PREFIX_UPPER = 'MDPSC';
	public const MACHINE_PREFIX = self::RUNTIME_PREFIX.'_';
	public const HTML_PREFIX = self::RUNTIME_PREFIX.'-';

	public static function machineIdentifier( string $suffix ) :string {
		return self::MACHINE_PREFIX.$suffix;
	}

	public static function htmlIdentifier( string $suffix ) :string {
		return self::HTML_PREFIX.$suffix;
	}

	public static function upperMachineIdentifier( string $suffix ) :string {
		return self::RUNTIME_PREFIX_UPPER.'_'.$suffix;
	}
}
