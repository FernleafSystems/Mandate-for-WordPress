<?php

declare( strict_types=1 );

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

$rootDir = dirname( __DIR__ );
$autoload = $rootDir.'/vendor/autoload.php';
if ( !is_file( $autoload ) ) {
	fwrite( STDERR, 'Missing vendor/autoload.php. Run composer install before installing WordPress tests.'.PHP_EOL );
	exit( 1 );
}

require $autoload;

$filesystem = new Filesystem();
$coreDir = wpm_resolve_wp_core_dir( $rootDir );

try {
	if ( !wpm_wp_core_valid( $coreDir ) ) {
		wpm_install_wordpress_core( $coreDir, $filesystem );
	}

	echo 'WordPress core ready at: '.$coreDir.PHP_EOL;
}
catch ( Throwable $throwable ) {
	fwrite( STDERR, 'WordPress test install failed: '.$throwable->getMessage().PHP_EOL );
	exit( 1 );
}

function wpm_resolve_wp_core_dir( string $rootDir ) :string {
	$fromEnv = getenv( 'WP_CORE_DIR' );
	if ( is_string( $fromEnv ) && $fromEnv !== '' ) {
		return Path::canonicalize( $fromEnv );
	}

	$localReference = Path::canonicalize( Path::join( $rootDir, '..', '..', '..', 'Libraries', 'wordpress' ) );
	if ( is_dir( $localReference ) ) {
		return $localReference;
	}

	return Path::join( rtrim( sys_get_temp_dir(), "\\/" ), 'mandate-wordpress' );
}

function wpm_wp_core_valid( string $coreDir ) :bool {
	return is_file( Path::join( $coreDir, 'wp-load.php' ) )
		&& is_file( Path::join( $coreDir, 'wp-includes/version.php' ) );
}

function wpm_install_wordpress_core( string $coreDir, Filesystem $filesystem ) :void {
	$tempRoot = Path::join( rtrim( sys_get_temp_dir(), "\\/" ), 'mandate-wordpress-download-'.bin2hex( random_bytes( 4 ) ) );
	$zipPath = Path::join( $tempRoot, 'wordpress.zip' );

	if ( !Path::isBasePath( rtrim( sys_get_temp_dir(), "\\/" ), $coreDir ) ) {
		throw new RuntimeException(
			'WordPress core is missing at '.$coreDir.'. Set WP_CORE_DIR to an existing WordPress checkout or allow the default temp path.'
		);
	}

	try {
		$filesystem->remove( $coreDir );
		$filesystem->mkdir( $tempRoot );

		$data = file_get_contents( 'https://wordpress.org/latest.zip' );
		if ( !is_string( $data ) || $data === '' ) {
			throw new RuntimeException( 'Failed to download https://wordpress.org/latest.zip.' );
		}
		$filesystem->dumpFile( $zipPath, $data );

		$zip = new ZipArchive();
		if ( $zip->open( $zipPath ) !== true ) {
			throw new RuntimeException( 'Failed to open downloaded WordPress archive.' );
		}
		try {
			$zip->extractTo( $tempRoot );
		}
		finally {
			$zip->close();
		}

		$extracted = Path::join( $tempRoot, 'wordpress' );
		if ( !wpm_wp_core_valid( $extracted ) ) {
			throw new RuntimeException( 'Downloaded WordPress archive did not contain a valid core tree.' );
		}

		$filesystem->rename( $extracted, $coreDir, true );
	}
	finally {
		$filesystem->remove( $tempRoot );
	}
}
