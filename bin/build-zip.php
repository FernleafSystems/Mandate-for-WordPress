#!/usr/bin/env php
<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Mandate\Tooling\CommandRunner;
use FernleafSystems\Wordpress\Plugin\Mandate\Tooling\RuntimePackageBuilder;
use FernleafSystems\Wordpress\Plugin\Mandate\Tooling\TemporaryDirectoryManager;
use FernleafSystems\Wordpress\Plugin\Mandate\Tooling\ZipBuilder;
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
	'keep-package',
] );

$projectRoot = Path::normalize( $projectRoot );
$buildDir = Path::join( $projectRoot, 'build' );
$keepPackage = isset( $options[ 'keep-package' ] );
$temporaryRoot = null;
$packageDir = null;
$logger = static function ( string $message ) :void {
	echo $message.PHP_EOL;
};
$filesystem = new Filesystem();
$temporaryDirectoryManager = new TemporaryDirectoryManager( $filesystem );

$exitCode = 0;
try {
	$outputZip = resolve_output_zip( $options[ 'output' ] ?? null, $projectRoot, $buildDir );
	$commandRunner = new CommandRunner( $projectRoot, $logger );
	$packageBuilder = new RuntimePackageBuilder( $projectRoot, $commandRunner, $filesystem, $logger );
	$zipBuilder = new ZipBuilder( $filesystem, $logger );

	$temporaryRoot = $temporaryDirectoryManager->create( 'mandate-build' );
	$packageDir = Path::join( $temporaryRoot, RuntimePackageBuilder::PLUGIN_SLUG );

	$packageBuilder->build( $packageDir, $temporaryRoot );
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

function resolve_output_zip( mixed $output, string $projectRoot, string $buildDir ) :string {
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
		$resolved = Path::join(
			$buildDir,
			RuntimePackageBuilder::PLUGIN_SLUG.'-'.\date( 'Ymd-His' ).'.zip'
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
