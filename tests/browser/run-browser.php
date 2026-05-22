<?php

declare( strict_types=1 );

$rootDir = dirname( __DIR__, 2 );
$args = array_slice( $_SERVER[ 'argv' ] ?? [], 1 );
$mode = 'warm';
$playwrightArgs = [];
$playwrightMode = false;

foreach ( $args as $arg ) {
	if ( $arg === '--' ) {
		$playwrightMode = true;
		continue;
	}
	if ( !$playwrightMode && $arg === '--clean' ) {
		$mode = 'clean';
		continue;
	}
	if ( !$playwrightMode && $arg === '--warm' ) {
		$mode = 'warm';
		continue;
	}
	$playwrightArgs[] = $arg;
}

$compose = [
	'docker',
	'compose',
	'-p',
	'mandate-browser',
	'-f',
	'tests/docker/docker-compose.browser.yml',
];

if ( $mode === 'clean' ) {
	wpm_run( array_merge( $compose, [ 'down', '-v', '--remove-orphans' ] ), $rootDir );
}

$baseUrl = getenv( 'WPM_BROWSER_BASE_URL' ) ?: 'http://127.0.0.1:8898';

wpm_run( array_merge( $compose, [ 'up', '-d', 'db' ] ), $rootDir );
wpm_run(
	array_merge(
		$compose,
		[
			'run',
			'--rm',
			'-T',
			'--entrypoint',
			'sh',
			'wp-cli',
			'-c',
			'tar -C /wordpress-src --exclude=.git -cf - . | tar -C /var/www/html -xf - && mkdir -p /var/www/html/wp-content/plugins',
		]
	),
	$rootDir
);
wpm_run( array_merge( $compose, [ 'up', '-d', 'wordpress' ] ), $rootDir );
wpm_wait_for_http_ready( $baseUrl.'/wp-login.php', 90 );
wpm_run(
	array_merge(
		$compose,
		[ 'run', '--rm', '-T', 'wp-cli', 'sh', '/app/tests/docker/provision-browser-site.sh' ]
	),
	$rootDir,
	[
		'WPM_BROWSER_SITE_URL' => $baseUrl,
	]
);

$npx = PHP_OS_FAMILY === 'Windows' ? 'npx.cmd' : 'npx';
wpm_run(
	array_merge( [ $npx, 'playwright', 'test' ], $playwrightArgs ),
	$rootDir,
	[
		'WPM_BROWSER_BASE_URL' => $baseUrl,
	]
);

/**
 * @param string[] $command
 * @param array<string,string> $env
 */
function wpm_run( array $command, string $cwd, array $env = [] ) :void {
	echo '> '.implode( ' ', array_map( 'wpm_quote_arg', $command ) ).PHP_EOL;
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
		array_merge( $baseEnv, $env )
	);
	if ( !is_resource( $process ) ) {
		throw new RuntimeException( 'Failed to start command.' );
	}

	$exitCode = proc_close( $process );
	if ( $exitCode !== 0 ) {
		exit( $exitCode );
	}
}

function wpm_quote_arg( string $arg ) :string {
	return preg_match( '/\s/', $arg ) === 1 ? '"'.$arg.'"' : $arg;
}

function wpm_wait_for_http_ready( string $url, int $timeoutSeconds ) :void {
	echo '> waiting for '.$url.PHP_EOL;
	$deadline = time() + $timeoutSeconds;

	do {
		$status = wpm_http_status( $url );
		if ( $status !== null && $status < 500 ) {
			return;
		}
		sleep( 2 );
	} while ( time() < $deadline );

	fwrite( STDERR, 'Browser test site did not serve '.$url.' within '.$timeoutSeconds.' seconds.'.PHP_EOL );
	exit( 1 );
}

function wpm_http_status( string $url ) :?int {
	if ( extension_loaded( 'curl' ) ) {
		$handle = curl_init( $url );
		if ( $handle === false ) {
			return null;
		}
		curl_setopt_array(
			$handle,
			[
				CURLOPT_NOBODY         => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 3,
				CURLOPT_FOLLOWLOCATION => false,
			]
		);
		curl_exec( $handle );
		$status = (int)curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
		curl_close( $handle );

		return $status > 0 ? $status : null;
	}

	$context = stream_context_create(
		[
			'http' => [
				'method'        => 'HEAD',
				'timeout'       => 3,
				'ignore_errors' => true,
			],
		]
	);
	$headers = @get_headers( $url, false, $context );
	if ( !is_array( $headers ) || empty( $headers[ 0 ] ) ) {
		return null;
	}

	return preg_match( '/\s(\d{3})\s/', $headers[ 0 ], $matches ) === 1 ? (int)$matches[ 1 ] : null;
}
