#!/usr/bin/env php
<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Tooling\ReleasePackageIdentity;

$projectRoot = dirname( __DIR__ );
if ( !\defined( 'ABSPATH' ) ) {
	\define( 'ABSPATH', $projectRoot.'/' );
}

$autoload = $projectRoot.'/vendor/autoload.php';
if ( !\is_file( $autoload ) ) {
	\fwrite( \STDERR, 'Missing vendor/autoload.php. Run composer install before resolving release zip names.'.PHP_EOL );
	exit( 1 );
}

require $autoload;

$options = \getopt( '', [
	'variant:',
	'tag:',
] );

$exitCode = 0;
try {
	$variant = $options[ 'variant' ] ?? null;
	$tag = $options[ 'tag' ] ?? null;
	if ( !\is_string( $variant ) || \trim( $variant ) === '' ) {
		throw new \RuntimeException( 'Missing required --variant option.' );
	}
	if ( !\is_string( $tag ) || \trim( $tag ) === '' ) {
		throw new \RuntimeException( 'Missing required --tag option.' );
	}

	echo ReleasePackageIdentity::zipName( \trim( $variant ), \trim( $tag ) ).PHP_EOL;
}
catch ( \Throwable $throwable ) {
	\fwrite( \STDERR, 'Release zip name failed: '.$throwable->getMessage().PHP_EOL );
	$exitCode = 1;
}

exit( $exitCode );
