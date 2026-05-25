#!/usr/bin/env php
<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Mandate\Tooling\CommandRunner;
use FernleafSystems\Wordpress\Plugin\Mandate\Tooling\RuntimePackageBuilder;
use FernleafSystems\Wordpress\Plugin\Mandate\Tooling\TemporaryDirectoryManager;
use FernleafSystems\Wordpress\Plugin\Mandate\Tooling\ZipBuilder;
use FernleafSystems\Wordpress\Plugin\Mandate\PluginIdentity;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

$projectRoot = dirname( __DIR__ );
$autoload = $projectRoot.'/vendor/autoload.php';
if ( !\is_file( $autoload ) ) {
	\fwrite( \STDERR, 'Missing vendor/autoload.php. Run composer install before building the zip.'.PHP_EOL );
	exit( 1 );
}

require $autoload;

foreach ( [
	\Symfony\Component\Filesystem\Filesystem::class,
	Path::class,
	\Symfony\Component\Process\Process::class,
] as $requiredClass ) {
	if ( !\class_exists( $requiredClass ) ) {
		\fwrite( \STDERR, 'Missing build dependency: '.$requiredClass.'. Run composer install.'.PHP_EOL );
		exit( 1 );
	}
}

if ( !\class_exists( \ZipArchive::class ) ) {
	\fwrite( \STDERR, 'Missing PHP ZipArchive support. Install/enable the zip extension.'.PHP_EOL );
	exit( 1 );
}

$options = \getopt( '', [
	'output::',
	'variant::',
	'keep-package',
	'skip-assets',
] );

$projectRoot = Path::normalize( $projectRoot );
$buildDir = Path::join( $projectRoot, 'build' );
$keepPackage = isset( $options[ 'keep-package' ] );
$buildAssets = !isset( $options[ 'skip-assets' ] );
$temporaryRoot = null;
$packageDir = null;
$logger = static function ( string $message ) :void {
	echo $message.PHP_EOL;
};
$filesystem = new Filesystem();
$temporaryDirectoryManager = new TemporaryDirectoryManager( $filesystem );

$exitCode = 0;
try {
	$variant = resolve_package_variant( $options[ 'variant' ] ?? null );
	$outputZip = resolve_output_zip( $options[ 'output' ] ?? null, $projectRoot, $buildDir, $variant );
	$commandRunner = new CommandRunner( $projectRoot, $logger );
	$packageBuilder = new RuntimePackageBuilder( $projectRoot, $commandRunner, $filesystem, $logger );
	$zipBuilder = new ZipBuilder( $filesystem, $logger );

	$temporaryRoot = $temporaryDirectoryManager->create( 'mandate-build' );
	$packageDir = Path::join( $temporaryRoot, RuntimePackageBuilder::PLUGIN_SLUG );

	$packageBuilder->build( $packageDir, $temporaryRoot, $buildAssets, $variant );
	$zipBuilder->build( $packageDir, $outputZip, RuntimePackageBuilder::PLUGIN_SLUG );
}
catch ( \Throwable $throwable ) {
	\fwrite( \STDERR, 'Build failed: '.$throwable->getMessage().PHP_EOL );
	$exitCode = 1;
}
finally {
	if ( $temporaryRoot !== null ) {
		if ( $keepPackage ) {
			echo 'Package retained at: '.( $packageDir ?? $temporaryRoot ).PHP_EOL;
		}
		else {
			try {
				$temporaryDirectoryManager->remove( $temporaryRoot );
			}
			catch ( \Throwable $throwable ) {
				\fwrite( \STDERR, 'Build temp cleanup failed: '.$throwable->getMessage().PHP_EOL );
				$exitCode = 1;
			}
		}
	}
}

exit( $exitCode );

function resolve_package_variant( mixed $variant ) :string {
	if ( !\is_string( $variant ) || \trim( $variant ) === '' ) {
		return RuntimePackageBuilder::VARIANT_WORDPRESS_ORG;
	}

	return \trim( $variant, " \t\n\r\0\x0B\"'" );
}

function resolve_output_zip( mixed $output, string $projectRoot, string $buildDir, string $variant ) :string {
	if ( \is_string( $output ) && \trim( $output ) !== '' ) {
		$output = \trim( $output, " \t\n\r\0\x0B\"'" );
		if ( Path::isAbsolute( $output ) ) {
			$resolved = Path::normalize( $output );
		}
		elseif ( \str_starts_with( \str_replace( '\\', '/', $output ), 'build/' ) ) {
			$resolved = Path::join( $projectRoot, $output );
		}
		else {
			$resolved = Path::join( $buildDir, $output );
		}
	}
	else {
		$slug = $variant === RuntimePackageBuilder::VARIANT_GITHUB
			? PluginIdentity::GITHUB_ASSET_PREFIX
			: RuntimePackageBuilder::PLUGIN_SLUG;
		$resolved = Path::join(
			$buildDir,
			$slug.'-'.\date( 'Ymd-His' ).'.zip'
		);
	}

	$resolved = Path::normalize( $resolved );
	if ( !\str_ends_with( \strtolower( $resolved ), '.zip' ) ) {
		throw new \RuntimeException( 'Output path must end with .zip: '.$resolved );
	}

	if ( !Path::isBasePath( $buildDir, $resolved ) ) {
		throw new \RuntimeException( 'Output zip must be inside the build directory: '.$resolved );
	}

	return $resolved;
}
