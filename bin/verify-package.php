#!/usr/bin/env php
<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Mandate\PluginIdentity;

if ( !\defined( 'ABSPATH' ) ) {
	\define( 'ABSPATH', \dirname( __DIR__ ).'/' );
}

require_once dirname( __DIR__ ).'/src/PluginIdentity.php';

const MANDATE_PACKAGE_ROOT = PluginIdentity::PACKAGE_ROOT;
const MANDATE_MAIN_PLUGIN_FILE = PluginIdentity::MAIN_FILE;
const MANDATE_VARIANT_WORDPRESS_ORG = 'wordpress-org';
const MANDATE_VARIANT_GITHUB = 'github';
const MANDATE_GITHUB_UPDATE_URI = 'Update URI: https://github.com/FernleafSystems/Mandate-for-WordPress';
const MANDATE_GITHUB_UPDATER_DEPENDENCY = 'yahnis-elsts/plugin-update-checker';
const MANDATE_GITHUB_UPDATER_VERSION = '^5.6';

$options = \getopt( '', [
	'variant:',
	'zip:',
] );

$exitCode = 0;
try {
	$variant = mandate_verify_package_variant( $options[ 'variant' ] ?? null );
	$zipPath = mandate_verify_zip_path( $options[ 'zip' ] ?? null );

	if ( !\class_exists( \ZipArchive::class ) ) {
		throw new \RuntimeException( 'Missing PHP ZipArchive support. Install/enable the zip extension.' );
	}

	$zip = new ZipArchive();
	$result = $zip->open( $zipPath );
	if ( $result !== true ) {
		throw new \RuntimeException( 'Failed to open package zip: '.$zipPath.' (error code: '.$result.')' );
	}

	try {
		$entries = mandate_verify_zip_entries( $zip );
		if ( $variant === MANDATE_VARIANT_WORDPRESS_ORG ) {
			mandate_verify_wordpress_org_package( $zip, $entries );
		}
		else {
			mandate_verify_github_package( $zip, $entries );
		}
	}
	finally {
		$zip->close();
	}

	echo 'Package verification passed for '.$variant.': '.$zipPath.PHP_EOL;
}
catch ( \Throwable $throwable ) {
	\fwrite( \STDERR, 'Package verification failed: '.$throwable->getMessage().PHP_EOL );
	$exitCode = 1;
}

exit( $exitCode );

function mandate_verify_package_variant( mixed $variant ) :string {
	if ( !\is_string( $variant ) || \trim( $variant ) === '' ) {
		throw new \RuntimeException( 'Missing required --variant option.' );
	}

	$variant = \trim( $variant, " \t\n\r\0\x0B\"'" );
	if ( \in_array( $variant, [ MANDATE_VARIANT_WORDPRESS_ORG, MANDATE_VARIANT_GITHUB ], true ) ) {
		return $variant;
	}

	throw new \RuntimeException( 'Unknown package variant: '.$variant );
}

function mandate_verify_zip_path( mixed $zipPath ) :string {
	if ( !\is_string( $zipPath ) || \trim( $zipPath ) === '' ) {
		throw new \RuntimeException( 'Missing required --zip option.' );
	}

	$zipPath = \trim( $zipPath, " \t\n\r\0\x0B\"'" );
	if ( !\is_file( $zipPath ) ) {
		throw new \RuntimeException( 'Package zip does not exist: '.$zipPath );
	}

	return $zipPath;
}

/**
 * @return string[]
 */
function mandate_verify_zip_entries( ZipArchive $zip ) :array {
	$entries = [];
	for ( $index = 0; $index < $zip->numFiles; $index++ ) {
		$name = $zip->getNameIndex( $index );
		if ( \is_string( $name ) ) {
			$entries[] = $name;
		}
	}

	foreach ( [
		MANDATE_PACKAGE_ROOT.MANDATE_MAIN_PLUGIN_FILE,
		MANDATE_PACKAGE_ROOT.'init.php',
		MANDATE_PACKAGE_ROOT.'composer.json',
		MANDATE_PACKAGE_ROOT.'src/PluginIdentity.php',
		MANDATE_PACKAGE_ROOT.'vendor/autoload.php',
	] as $requiredEntry ) {
		if ( !\in_array( $requiredEntry, $entries, true ) ) {
			throw new \RuntimeException( 'Package is missing required entry: '.$requiredEntry );
		}
	}

	return $entries;
}

/**
 * @param string[] $entries
 */
function mandate_verify_wordpress_org_package( ZipArchive $zip, array $entries ) :void {
	if ( \in_array( MANDATE_PACKAGE_ROOT.'github-updater.php', $entries, true ) ) {
		throw new \RuntimeException( 'WordPress.org package must not contain github-updater.php.' );
	}

	$require = mandate_verify_package_require( $zip );
	if ( \array_key_exists( MANDATE_GITHUB_UPDATER_DEPENDENCY, $require ) ) {
		throw new \RuntimeException( 'WordPress.org package must not require Plugin Update Checker.' );
	}

	$plugin = mandate_verify_read_entry( $zip, MANDATE_PACKAGE_ROOT.MANDATE_MAIN_PLUGIN_FILE );
	if ( \str_contains( $plugin, 'Update URI:' ) ) {
		throw new \RuntimeException( 'WordPress.org package must not contain an Update URI header.' );
	}

	$init = mandate_verify_read_entry( $zip, MANDATE_PACKAGE_ROOT.'init.php' );
	if ( \str_contains( $init, 'github-updater.php' ) ) {
		throw new \RuntimeException( 'WordPress.org package must not bootstrap the GitHub updater.' );
	}

	mandate_verify_no_updater_tokens( $zip, $entries );
}

/**
 * @param string[] $entries
 */
function mandate_verify_github_package( ZipArchive $zip, array $entries ) :void {
	if ( !\in_array( MANDATE_PACKAGE_ROOT.'github-updater.php', $entries, true ) ) {
		throw new \RuntimeException( 'GitHub package must contain github-updater.php.' );
	}

	$require = mandate_verify_package_require( $zip );
	if ( !\array_key_exists( MANDATE_GITHUB_UPDATER_DEPENDENCY, $require ) ) {
		throw new \RuntimeException( 'GitHub package must require Plugin Update Checker.' );
	}

	if ( $require[ MANDATE_GITHUB_UPDATER_DEPENDENCY ] !== MANDATE_GITHUB_UPDATER_VERSION ) {
		throw new \RuntimeException( 'GitHub package must require Plugin Update Checker '.MANDATE_GITHUB_UPDATER_VERSION.'.' );
	}

	$plugin = mandate_verify_read_entry( $zip, MANDATE_PACKAGE_ROOT.MANDATE_MAIN_PLUGIN_FILE );
	if ( !\str_contains( $plugin, MANDATE_GITHUB_UPDATE_URI ) ) {
		throw new \RuntimeException( 'GitHub package must contain the GitHub Update URI header.' );
	}

	$init = mandate_verify_read_entry( $zip, MANDATE_PACKAGE_ROOT.'init.php' );
	if ( !\str_contains( $init, "require_once __DIR__.'/github-updater.php';" ) ) {
		throw new \RuntimeException( 'GitHub package must bootstrap github-updater.php from init.php.' );
	}

	$updater = mandate_verify_read_entry( $zip, MANDATE_PACKAGE_ROOT.'github-updater.php' );
	foreach ( [
		'YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory',
		'https://github.com/FernleafSystems/Mandate-for-WordPress/',
		'PluginIdentity::MAIN_FILE',
		'PluginIdentity::SLUG',
		'PluginIdentity::GITHUB_ASSET_PREFIXES',
	] as $token ) {
		if ( !\str_contains( $updater, $token ) ) {
			throw new \RuntimeException( 'GitHub updater bootstrap is missing expected token: '.$token );
		}
	}

	$identity = mandate_verify_read_entry( $zip, MANDATE_PACKAGE_ROOT.'src/PluginIdentity.php' );
	foreach ( [
		'GITHUB_ASSET_PREFIX',
		'LEGACY_GITHUB_ASSET_PREFIX',
		'GITHUB_ASSET_PREFIXES',
	] as $token ) {
		if ( !\str_contains( $identity, $token ) ) {
			throw new \RuntimeException( 'GitHub package identity is missing expected token: '.$token );
		}
	}

	$hasVendoredUpdater = false;
	foreach ( $entries as $entry ) {
		if ( \str_starts_with( $entry, MANDATE_PACKAGE_ROOT.'vendor/yahnis-elsts/plugin-update-checker/' ) ) {
			$hasVendoredUpdater = true;
			break;
		}
	}

	if ( !$hasVendoredUpdater ) {
		throw new \RuntimeException( 'GitHub package must contain the vendored Plugin Update Checker package.' );
	}
}

/**
 * @return array<string,mixed>
 */
function mandate_verify_package_composer( ZipArchive $zip ) :array {
	$composerJson = mandate_verify_read_entry( $zip, MANDATE_PACKAGE_ROOT.'composer.json' );
	$composer = \json_decode( $composerJson, true );
	if ( !\is_array( $composer ) ) {
		throw new \RuntimeException( 'Package composer.json is invalid JSON: '.\json_last_error_msg() );
	}

	if ( !isset( $composer[ 'require' ] ) || !\is_array( $composer[ 'require' ] ) ) {
		throw new \RuntimeException( 'Package composer.json must contain a require object.' );
	}

	return $composer;
}

/**
 * @return array<string,mixed>
 */
function mandate_verify_package_require( ZipArchive $zip ) :array {
	$composer = mandate_verify_package_composer( $zip );
	return $composer[ 'require' ];
}

/**
 * @param string[] $entries
 */
function mandate_verify_no_updater_tokens( ZipArchive $zip, array $entries ) :void {
	$tokens = [
		'YahnisElsts',
		'PluginUpdateChecker',
		'PucFactory',
		'plugin-update-checker',
		...array_map(
			static fn( string $assetPrefix ) :string => $assetPrefix.'-',
			PluginIdentity::GITHUB_ASSET_PREFIXES
		),
	];

	foreach ( $entries as $entry ) {
		if ( \str_ends_with( $entry, '/' ) ) {
			continue;
		}

		$content = mandate_verify_read_entry( $zip, $entry );
		foreach ( $tokens as $token ) {
			if ( \str_contains( $content, $token ) ) {
				throw new \RuntimeException( 'WordPress.org package contains updater token "'.$token.'" in '.$entry.'.' );
			}
		}
	}
}

function mandate_verify_read_entry( ZipArchive $zip, string $entry ) :string {
	$content = $zip->getFromName( $entry );
	if ( !\is_string( $content ) ) {
		throw new \RuntimeException( 'Failed to read package entry: '.$entry );
	}

	return $content;
}
