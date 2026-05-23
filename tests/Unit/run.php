<?php

declare( strict_types=1 );

$rootDir = dirname( __DIR__, 2 );
$phpunit = $rootDir.'/vendor/bin/phpunit';
if ( PHP_OS_FAMILY === 'Windows' ) {
	$phpunit .= '.bat';
}

if ( !is_file( $phpunit ) ) {
	fwrite( STDERR, 'PHPUnit is missing. Run composer install before running tests.'.PHP_EOL );
	exit( 1 );
}

$command = array_merge(
	[
		$phpunit,
		'-c',
		$rootDir.'/phpunit-unit.xml',
	],
	array_slice( $_SERVER[ 'argv' ] ?? [], 1 )
);

$descriptorSpec = [
	0 => [ 'file', 'php://stdin', 'r' ],
	1 => [ 'file', 'php://stdout', 'w' ],
	2 => [ 'file', 'php://stderr', 'w' ],
];

$process = proc_open( $command, $descriptorSpec, $pipes, $rootDir );
if ( !is_resource( $process ) ) {
	fwrite( STDERR, 'Failed to start PHPUnit.'.PHP_EOL );
	exit( 1 );
}

exit( proc_close( $process ) );
