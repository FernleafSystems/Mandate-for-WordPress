<?php

declare( strict_types=1 );

$rootDir = dirname( __DIR__, 2 );
$args = array_slice( $_SERVER[ 'argv' ] ?? [], 1 );
$clean = in_array( '--clean', $args, true );
$dbDown = in_array( '--db-down', $args, true );
$phpunitArgs = [];
$phpunitMode = false;

foreach ( $args as $arg ) {
	if ( $arg === '--' ) {
		$phpunitMode = true;
		continue;
	}
	if ( !$phpunitMode && ( $arg === '--clean' || $arg === '--db-down' ) ) {
		continue;
	}
	$phpunitArgs[] = $arg;
}

$compose = [
	'docker',
	'compose',
	'-p',
	'mandate-integration',
	'-f',
	'tests/docker/docker-compose.integration.yml',
];

if ( $clean || $dbDown ) {
	wpm_integration_run( array_merge( $compose, [ 'down', '-v', '--remove-orphans' ] ), $rootDir );
	if ( $dbDown ) {
		exit( 0 );
	}
}

wpm_integration_run( array_merge( $compose, [ 'up', '-d', 'db' ] ), $rootDir );
wpm_integration_wait_for_db( $compose, $rootDir, 90 );
wpm_integration_run( [ PHP_BINARY, 'bin/install-wp-tests.php' ], $rootDir );

$phpunit = PHP_OS_FAMILY === 'Windows' ? 'vendor/bin/phpunit.bat' : 'vendor/bin/phpunit';
wpm_integration_run(
	array_merge( [ $phpunit, '-c', 'phpunit-integration.xml' ], $phpunitArgs ),
	$rootDir,
	[
		'WPM_INTEGRATION_DB_HOST' => wpm_integration_db_host(),
	]
);

/**
 * @param string[] $command
 * @param array<string,string> $env
 */
function wpm_integration_run( array $command, string $cwd, array $env = [] ) :void {
	echo '> '.implode( ' ', array_map( 'wpm_integration_quote_arg', $command ) ).PHP_EOL;
	$descriptorSpec = [
		0 => [ 'file', 'php://stdin', 'r' ],
		1 => [ 'file', 'php://stdout', 'w' ],
		2 => [ 'file', 'php://stderr', 'w' ],
	];
	$baseEnv = getenv();
	if ( !is_array( $baseEnv ) ) {
		$baseEnv = [];
	}
	$process = proc_open(
		$command,
		$descriptorSpec,
		$pipes,
		$cwd,
		array_merge( $baseEnv, $env, [ 'COMPOSE_PROGRESS' => 'quiet' ] )
	);
	if ( !is_resource( $process ) ) {
		throw new RuntimeException( 'Failed to start command.' );
	}

	$exitCode = proc_close( $process );
	if ( $exitCode !== 0 ) {
		exit( $exitCode );
	}
}

/**
 * @param string[] $compose
 */
function wpm_integration_wait_for_db( array $compose, string $cwd, int $timeoutSeconds ) :void {
	$deadline = time() + $timeoutSeconds;
	do {
		$exitCode = wpm_integration_run_for_exit_code(
			array_merge( $compose, [ 'exec', '-T', 'db', 'mysqladmin', 'ping', '-h', '127.0.0.1', '-P', '3306', '-uroot', '-ptestpass' ] ),
			$cwd
		);
		if ( $exitCode === 0 ) {
			return;
		}
		sleep( 2 );
	} while ( time() < $deadline );

	fwrite( STDERR, 'Integration test database did not become ready within '.$timeoutSeconds.' seconds.'.PHP_EOL );
	exit( 1 );
}

/**
 * @param string[] $command
 */
function wpm_integration_run_for_exit_code( array $command, string $cwd ) :int {
	$descriptorSpec = [
		0 => [ 'file', 'php://stdin', 'r' ],
		1 => [ 'file', 'NUL', 'w' ],
		2 => [ 'file', 'NUL', 'w' ],
	];
	if ( PHP_OS_FAMILY !== 'Windows' ) {
		$descriptorSpec[ 1 ] = [ 'file', '/dev/null', 'w' ];
		$descriptorSpec[ 2 ] = [ 'file', '/dev/null', 'w' ];
	}

	$process = proc_open( $command, $descriptorSpec, $pipes, $cwd );
	if ( !is_resource( $process ) ) {
		return 1;
	}

	return proc_close( $process );
}

function wpm_integration_db_host() :string {
	$host = getenv( 'WPM_INTEGRATION_DB_HOST' );
	if ( is_string( $host ) && $host !== '' ) {
		return $host;
	}

	$port = getenv( 'WPM_INTEGRATION_DB_PORT' );
	return '127.0.0.1:'.( is_string( $port ) && $port !== '' ? $port : '3312' );
}

function wpm_integration_quote_arg( string $arg ) :string {
	return preg_match( '/\s/', $arg ) === 1 ? '"'.$arg.'"' : $arg;
}
