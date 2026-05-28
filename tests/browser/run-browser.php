<?php

declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Tooling\ProcessRunner;

const WPM_BROWSER_READY_MARKER = '/var/www/html/wp-content/.mandate-browser-lane-ready.json';
const WPM_BROWSER_READY_SCHEMA_VERSION = 1;
const WPM_BROWSER_DB_ROOT_PASSWORD = 'testpass';

$rootDir = dirname( __DIR__, 2 );
$autoload = $rootDir.'/vendor/autoload.php';
if ( !is_file( $autoload ) ) {
	fwrite( STDERR, 'Missing vendor/autoload.php. Run composer install before browser tests.'.PHP_EOL );
	exit( 1 );
}

require $autoload;

$args = array_slice( $_SERVER[ 'argv' ] ?? [], 1 );
$mode = 'warm';
$laneCount = getenv( 'CI' ) ? 1 : 2;
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
	if ( !$playwrightMode && preg_match( '/^--lanes=(\d+)$/', $arg, $matches ) === 1 ) {
		$laneCount = wpm_positive_int( $matches[ 1 ], '--lanes' );
		continue;
	}
	if ( !$playwrightMode && $arg === '--lanes' ) {
		fwrite( STDERR, 'Use --lanes=N for browser lane count.'.PHP_EOL );
		exit( 1 );
	}
	$playwrightArgs[] = $arg;
}

$workerCount = wpm_resolve_worker_count( $playwrightArgs, $laneCount );
if ( $workerCount > $laneCount ) {
	fwrite( STDERR, sprintf( 'Browser workers (%d) cannot exceed available lanes (%d). Use --lanes or reduce --workers.', $workerCount, $laneCount ).PHP_EOL );
	exit( 1 );
}
wpm_reject_playwright_shard( $playwrightArgs );
$playwrightArgs = wpm_without_playwright_workers( $playwrightArgs );

$dbCompose = [
	'docker',
	'compose',
	'-p',
	'mandate-browser-db',
	'-f',
	'tests/docker/docker-compose.browser-db.yml',
];

if ( $mode === 'clean' ) {
	wpm_run(
		[
			'docker',
			'compose',
			'-p',
			'mandate-browser',
			'-f',
			'tests/docker/docker-compose.browser.yml',
			'down',
			'-v',
			'--remove-orphans',
		],
		$rootDir
	);
}

wpm_run( array_merge( $dbCompose, [ 'up', '-d', 'db' ] ), $rootDir );
wpm_wait_for_database( $rootDir, $dbCompose );

$laneMap = [];
for ( $laneIndex = 1; $laneIndex <= $workerCount; $laneIndex++ ) {
	$port = 8897 + $laneIndex;
	$baseUrl = 'http://127.0.0.1:'.$port;
	$dbName = 'wordpress_browser_lane_'.$laneIndex;
	$compose = wpm_lane_compose( $laneIndex );
	$env = wpm_lane_env( $laneIndex, $baseUrl, $dbName, $port );

	if ( $mode === 'clean' ) {
		wpm_run( array_merge( $compose, [ 'down', '-v', '--remove-orphans' ] ), $rootDir, $env );
		wpm_reset_database( $rootDir, $dbCompose, $dbName );
	}
	else {
		wpm_ensure_database( $rootDir, $dbCompose, $dbName );
	}

	$needsSeed = $mode === 'clean' || !wpm_lane_ready( $rootDir, $compose, $env, $baseUrl, $dbName );
	if ( $needsSeed ) {
		echo '> seeding WordPress core for browser lane '.$laneIndex.PHP_EOL;
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
			$rootDir,
			$env
		);
	}
	else {
		echo '> browser lane '.$laneIndex.' WordPress core is warm; skipping seed'.PHP_EOL;
	}

	wpm_run( array_merge( $compose, [ 'up', '-d', 'wordpress' ] ), $rootDir, $env );
	wpm_wait_for_http_ready( $baseUrl.'/wp-login.php', 90 );
	wpm_run(
		array_merge(
			$compose,
			[ 'run', '--rm', '-T', 'wp-cli', 'sh', '/app/tests/docker/provision-browser-site.sh' ]
		),
		$rootDir,
		$env
	);
	wpm_write_ready_marker( $rootDir, $compose, $env, $baseUrl, $dbName );

	$laneMap[ (string)( $laneIndex - 1 ) ] = [
		'laneIndex'     => $laneIndex,
		'baseUrl'       => $baseUrl,
		'outputDir'     => './test-results/playwright/lane-'.$laneIndex.'/artifacts',
		'htmlReportDir' => './test-results/playwright/lane-'.$laneIndex.'/html-report',
	];
}

$npx = PHP_OS_FAMILY === 'Windows' ? 'npx.cmd' : 'npx';
wpm_run_playwright_lanes( $rootDir, $npx, $playwrightArgs, $laneMap, $workerCount );

/**
 * @return string[]
 */
function wpm_lane_compose( int $laneIndex ) :array {
	return [
		'docker',
		'compose',
		'-p',
		'mandate-browser-lane-'.$laneIndex,
		'-f',
		'tests/docker/docker-compose.browser.yml',
	];
}

/**
 * @return array<string,string>
 */
function wpm_lane_env( int $laneIndex, string $baseUrl, string $dbName, int $port ) :array {
	return [
		'WPM_BROWSER_BASE_URL'   => $baseUrl,
		'WPM_BROWSER_SITE_URL'   => $baseUrl,
		'WPM_BROWSER_DB_HOST'    => 'mandate-browser-db:3306',
		'WPM_BROWSER_DB_NAME'    => $dbName,
		'WPM_BROWSER_PORT'       => (string)$port,
		'WPM_BROWSER_PLUGIN_SLUG' => 'mandate-app-security',
	];
}

/**
 * @param string[] $dbCompose
 */
function wpm_reset_database( string $rootDir, array $dbCompose, string $dbName ) :void {
	if ( preg_match( '/^[a-z0-9_]+$/', $dbName ) !== 1 ) {
		throw new RuntimeException( 'Unsafe browser database name: '.$dbName );
	}

	wpm_run(
		array_merge(
			$dbCompose,
			[
				'exec',
				'-T',
				'db',
				'mysql',
				'-uroot',
				'-p'.WPM_BROWSER_DB_ROOT_PASSWORD,
				'-e',
				sprintf( 'DROP DATABASE IF EXISTS `%1$s`; CREATE DATABASE `%1$s`;', $dbName ),
			]
		),
		$rootDir
	);
}

/**
 * @param string[] $dbCompose
 */
function wpm_ensure_database( string $rootDir, array $dbCompose, string $dbName ) :void {
	if ( preg_match( '/^[a-z0-9_]+$/', $dbName ) !== 1 ) {
		throw new RuntimeException( 'Unsafe browser database name: '.$dbName );
	}

	wpm_run(
		array_merge(
			$dbCompose,
			[
				'exec',
				'-T',
				'db',
				'mysql',
				'-uroot',
				'-p'.WPM_BROWSER_DB_ROOT_PASSWORD,
				'-e',
				sprintf( 'CREATE DATABASE IF NOT EXISTS `%1$s`;', $dbName ),
			]
		),
		$rootDir
	);
}

/**
 * @param string[] $dbCompose
 */
function wpm_wait_for_database( string $rootDir, array $dbCompose ) :void {
	$deadline = time() + 90;
	do {
		$result = wpm_capture(
			array_merge(
				$dbCompose,
				[ 'exec', '-T', 'db', 'mysqladmin', 'ping', '-h', 'localhost', '-uroot', '-p'.WPM_BROWSER_DB_ROOT_PASSWORD ]
			),
			$rootDir
		);
		if ( $result[ 'exit_code' ] === 0 ) {
			return;
		}
		sleep( 2 );
	} while ( time() < $deadline );

	fwrite( STDERR, 'Browser test database did not become ready within 90 seconds.'.PHP_EOL );
	exit( 1 );
}

/**
 * @param string[]             $compose
 * @param array<string,string> $env
 */
function wpm_lane_ready( string $rootDir, array $compose, array $env, string $baseUrl, string $dbName ) :bool {
	$result = wpm_capture(
		array_merge(
			$compose,
			[
				'run',
				'--rm',
				'-T',
				'wp-cli',
				'sh',
				'-c',
				'if [ ! -f wp-load.php ] || [ ! -f '.escapeshellarg( WPM_BROWSER_READY_MARKER ).' ]; then exit 10; fi; wp core is-installed --allow-root >/dev/null 2>&1 && cat '.escapeshellarg( WPM_BROWSER_READY_MARKER ),
			]
		),
		$rootDir,
		$env
	);
	if ( $result[ 'exit_code' ] !== 0 ) {
		return false;
	}

	$decoded = json_decode( trim( $result[ 'stdout' ] ), true );
	if ( !is_array( $decoded ) ) {
		return false;
	}

	return (int)( $decoded[ 'schema_version' ] ?? 0 ) === WPM_BROWSER_READY_SCHEMA_VERSION
		&& (string)( $decoded[ 'site_url' ] ?? '' ) === $baseUrl
		&& (string)( $decoded[ 'db_name' ] ?? '' ) === $dbName
		&& (string)( $decoded[ 'admin_user' ] ?? '' ) === ( getenv( 'WPM_BROWSER_ADMIN_USER' ) ?: 'admin' )
		&& (string)( $decoded[ 'plugin_slug' ] ?? '' ) === 'mandate-app-security'
		&& (string)( $decoded[ 'wordpress_version' ] ?? '' ) !== '';
}

/**
 * @param string[]             $compose
 * @param array<string,string> $env
 */
function wpm_write_ready_marker( string $rootDir, array $compose, array $env, string $baseUrl, string $dbName ) :void {
	$version = trim( wpm_capture(
		array_merge( $compose, [ 'run', '--rm', '-T', 'wp-cli', 'wp', 'core', 'version', '--allow-root' ] ),
		$rootDir,
		$env
	)[ 'stdout' ] );
	$marker = json_encode(
		[
			'schema_version'    => WPM_BROWSER_READY_SCHEMA_VERSION,
			'site_url'          => $baseUrl,
			'db_name'           => $dbName,
			'admin_user'        => getenv( 'WPM_BROWSER_ADMIN_USER' ) ?: 'admin',
			'plugin_slug'       => 'mandate-app-security',
			'wordpress_version' => $version,
		],
		JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
	);

	wpm_run(
		array_merge(
			$compose,
			[
				'run',
				'--rm',
				'-T',
				'wp-cli',
				'php',
				'-r',
				'file_put_contents('.var_export( WPM_BROWSER_READY_MARKER, true ).', '.var_export( $marker, true ).');',
			]
		),
		$rootDir,
		$env
	);
}

/**
 * @param string[] $playwrightArgs
 */
function wpm_resolve_worker_count( array $playwrightArgs, int $laneCount ) :int {
	foreach ( $playwrightArgs as $index => $arg ) {
		if ( preg_match( '/^--workers=(\d+)$/', $arg, $matches ) === 1 || preg_match( '/^-j=(\d+)$/', $arg, $matches ) === 1 ) {
			return wpm_positive_int( $matches[ 1 ], $arg );
		}
		if ( $arg === '--workers' || $arg === '-j' ) {
			if ( !isset( $playwrightArgs[ $index + 1 ] ) ) {
				fwrite( STDERR, $arg.' must be followed by a positive integer.'.PHP_EOL );
				exit( 1 );
			}
			return wpm_positive_int( $playwrightArgs[ $index + 1 ], $arg );
		}
		if ( str_starts_with( $arg, '--workers' ) || str_starts_with( $arg, '-j' ) ) {
			fwrite( STDERR, $arg.' must be a positive integer worker option.'.PHP_EOL );
			exit( 1 );
		}
	}

	return $laneCount;
}

/**
 * @param string[] $playwrightArgs
 */
function wpm_reject_playwright_shard( array $playwrightArgs ) :void {
	foreach ( $playwrightArgs as $arg ) {
		if ( $arg === '--shard' || str_starts_with( $arg, '--shard=' ) ) {
			fwrite( STDERR, 'Browser runner owns Playwright sharding for lane output isolation. Use --lanes and --workers instead of --shard.'.PHP_EOL );
			exit( 1 );
		}
	}
}

/**
 * @param string[] $playwrightArgs
 * @return string[]
 */
function wpm_without_playwright_workers( array $playwrightArgs ) :array {
	$filtered = [];
	$skipNext = false;
	foreach ( $playwrightArgs as $arg ) {
		if ( $skipNext ) {
			$skipNext = false;
			continue;
		}
		if ( $arg === '--workers' || $arg === '-j' ) {
			$skipNext = true;
			continue;
		}
		if ( str_starts_with( $arg, '--workers=' ) || str_starts_with( $arg, '-j=' ) ) {
			continue;
		}

		$filtered[] = $arg;
	}

	return $filtered;
}

/**
 * @param string[] $playwrightArgs
 * @param array<string,array{laneIndex:int,baseUrl:string,outputDir:string,htmlReportDir:string}> $laneMap
 */
function wpm_run_playwright_lanes( string $rootDir, string $npx, array $playwrightArgs, array $laneMap, int $workerCount ) :void {
	$jobs = [];
	for ( $parallelIndex = 0; $parallelIndex < $workerCount; $parallelIndex++ ) {
		$lane = $laneMap[ (string)$parallelIndex ];
		$outputDir = $lane[ 'outputDir' ];
		$htmlReportDir = $lane[ 'htmlReportDir' ];
		$outputPath = $rootDir.'/'.ltrim( $outputDir, './\\' );
		if ( !is_dir( $outputPath ) ) {
			mkdir( $outputPath, 0777, true );
		}
		$htmlReportPath = $rootDir.'/'.ltrim( $htmlReportDir, './\\' );
		if ( !is_dir( $htmlReportPath ) ) {
			mkdir( $htmlReportPath, 0777, true );
		}

		$command = array_merge( [ $npx, 'playwright', 'test', '--workers=1' ], $playwrightArgs );
		if ( $workerCount > 1 ) {
			$command[] = '--shard='.($parallelIndex + 1).'/'.$workerCount;
		}

		$jobs[] = [
			'command'     => $command,
			'working_dir' => $rootDir,
			'env'         => [
				'WPM_BROWSER_LANE_MAP'      => json_encode( (object)[ '0' => $lane ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ),
				'WPM_BROWSER_OUTPUT_DIR'    => $outputDir,
				'PLAYWRIGHT_HTML_OUTPUT_DIR' => $htmlReportDir,
			],
			'label'       => 'browser lane '.$lane[ 'laneIndex' ],
		];
	}

	$results = wpm_process_runner()->runConcurrent( $jobs );
	foreach ( $results as $result ) {
		if ( $result[ 'exit_code' ] !== 0 ) {
			fwrite( STDERR, $result[ 'label' ].' failed with exit code '.$result[ 'exit_code' ].PHP_EOL );
			exit( $result[ 'exit_code' ] );
		}
	}
}

function wpm_positive_int( string $value, string $source ) :int {
	if ( !ctype_digit( $value ) || (int)$value < 1 ) {
		fwrite( STDERR, $source.' must be a positive integer.'.PHP_EOL );
		exit( 1 );
	}

	return (int)$value;
}

/**
 * @param string[]             $command
 * @param array<string,string> $env
 */
function wpm_run( array $command, string $cwd, array $env = [] ) :void {
	$exitCode = wpm_process_runner()->runForExitCode( $command, $cwd, null, $env );
	if ( $exitCode !== 0 ) {
		exit( $exitCode );
	}
}

/**
 * @param string[]             $command
 * @param array<string,string> $env
 * @return array{exit_code:int,stdout:string,stderr:string}
 */
function wpm_capture( array $command, string $cwd, array $env = [] ) :array {
	return wpm_process_runner()->runAndCapture( $command, $cwd, $env );
}

function wpm_process_runner() :ProcessRunner {
	static $runner = null;
	if ( !$runner instanceof ProcessRunner ) {
		$runner = new ProcessRunner();
	}

	return $runner;
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
