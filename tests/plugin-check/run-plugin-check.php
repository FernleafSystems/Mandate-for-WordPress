<?php

declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Mandate\Tooling\CommandRunner;
use FernleafSystems\Wordpress\Plugin\Mandate\Tooling\RuntimePackageBuilder;
use FernleafSystems\Wordpress\Plugin\Mandate\Tooling\TemporaryDirectoryManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

const WPM_PLUGIN_CHECK_DEFAULT_VERSION = '1.9.0';

$rootDir = dirname( __DIR__, 2 );
$autoload = $rootDir.'/vendor/autoload.php';
if ( !is_file( $autoload ) ) {
	fwrite( STDERR, "Missing vendor/autoload.php. Run composer install before Plugin Check.\n" );
	exit( 1 );
}

require $autoload;

$args = array_slice( $_SERVER[ 'argv' ] ?? [], 1 );
$mode = in_array( '--clean', $args, true ) ? 'clean' : 'warm';
$keepPackage = in_array( '--keep-package', $args, true );
$pluginCheckVersion = getenv( 'WPM_PLUGIN_CHECK_VERSION' ) ?: WPM_PLUGIN_CHECK_DEFAULT_VERSION;
$filesystem = new Filesystem();
$temporaryDirectoryManager = new TemporaryDirectoryManager( $filesystem );
$temporaryRoot = null;
$packageDir = null;
$exitCode = 0;

$compose = [
	'docker',
	'compose',
	'-p',
	'mandate-plugin-check',
	'-f',
	'tests/docker/docker-compose.plugin-check.yml',
];

try {
	$temporaryRoot = $temporaryDirectoryManager->create( 'mandate-plugin-check' );
	$packageDir = Path::join( $temporaryRoot, RuntimePackageBuilder::PLUGIN_SLUG );
	$filesystem->mkdir( $packageDir );
	$composeEnv = [
		'WPM_PLUGIN_CHECK_PACKAGE_DIR' => $packageDir,
	];

	if ( $mode === 'clean' ) {
		wpm_plugin_check_run( array_merge( $compose, [ 'down', '-v', '--remove-orphans' ] ), $rootDir, $composeEnv );
	}

	wpm_plugin_check_build_package( $rootDir, $temporaryRoot, $packageDir );

	wpm_plugin_check_run( array_merge( $compose, [ 'up', '-d', 'db' ] ), $rootDir, $composeEnv );
	wpm_plugin_check_run(
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
				'if [ ! -f /var/www/html/wp-includes/version.php ]; then tar -C /wordpress-src --exclude=.git -cf - . | tar -C /var/www/html -xf -; fi && mkdir -p /var/www/html/wp-content/plugins',
			]
		),
		$rootDir,
		$composeEnv
	);
	wpm_plugin_check_run(
		array_merge(
			$compose,
			[
				'run',
				'--rm',
				'-T',
				'--env',
				'WPM_PLUGIN_CHECK_VERSION='.$pluginCheckVersion,
				'wp-cli',
				'sh',
				'/app/tests/plugin-check/provision-site.sh',
			]
		),
		$rootDir,
		$composeEnv
	);

	$result = wpm_plugin_check_run_capture(
		array_merge(
			$compose,
			[
				'run',
				'--rm',
				'-T',
				'wp-cli',
				'wp',
				'plugin',
				'check',
				'mandate-app-security',
				'--format=json',
				'--require=./wp-content/plugins/plugin-check/cli.php',
				'--slug=mandate-app-security',
				'--allow-root',
			]
		),
		$rootDir,
		$composeEnv
	);

	if ( $result[ 'exit_code' ] !== 0 ) {
		echo $result[ 'stdout' ];
		fwrite( STDERR, $result[ 'stderr' ] );
		throw new RuntimeException( 'Plugin Check command failed with exit code '.$result[ 'exit_code' ].'.' );
	}

	$findings = wpm_plugin_check_parse_findings( $result[ 'stdout' ] );
	$errorCount = wpm_plugin_check_count_type( $findings, 'ERROR' );
	$warningCount = wpm_plugin_check_count_type( $findings, 'WARNING' );

	wpm_plugin_check_print_findings( $findings );
	echo sprintf(
		"Plugin Check completed with %d error%s and %d warning%s.\n",
		$errorCount,
		$errorCount === 1 ? '' : 's',
		$warningCount,
		$warningCount === 1 ? '' : 's'
	);

	$exitCode = $errorCount > 0 ? 1 : 0;
}
catch ( Throwable $throwable ) {
	fwrite( STDERR, 'Plugin Check failed: '.$throwable->getMessage().PHP_EOL );
	$exitCode = 1;
}
finally {
	if ( $temporaryRoot !== null ) {
		if ( $keepPackage ) {
			echo 'Plugin Check package retained at: '.( $packageDir ?? $temporaryRoot ).PHP_EOL;
		}
		else {
			try {
				$temporaryDirectoryManager->remove( $temporaryRoot );
			}
			catch ( Throwable $throwable ) {
				fwrite( STDERR, 'Plugin Check temp cleanup failed: '.$throwable->getMessage().PHP_EOL );
				$exitCode = 1;
			}
		}
	}
}

exit( $exitCode );

function wpm_plugin_check_build_package( string $rootDir, string $temporaryRoot, string $packageDir ) :void {
	$logger = static function ( string $message ) :void {
		echo $message.PHP_EOL;
	};

	( new RuntimePackageBuilder(
		$rootDir,
		new CommandRunner( $rootDir, $logger ),
		new Filesystem(),
		$logger
	) )->build( $packageDir, $temporaryRoot, false );
}

/**
 * @param string[] $command
 * @param array<string,string> $env
 */
function wpm_plugin_check_run( array $command, string $cwd, array $env = [] ) :void {
	$result = wpm_plugin_check_run_capture( $command, $cwd, $env, true );
	if ( $result[ 'exit_code' ] !== 0 ) {
		throw new RuntimeException( 'Command failed with exit code '.$result[ 'exit_code' ].': '.implode( ' ', $command ) );
	}
}

/**
 * @param string[] $command
 * @param array<string,string> $env
 * @return array{exit_code:int,stdout:string,stderr:string}
 */
function wpm_plugin_check_run_capture( array $command, string $cwd, array $env = [], bool $stream = false ) :array {
	echo '> '.implode( ' ', array_map( 'wpm_plugin_check_quote_arg', $command ) ).PHP_EOL;
	$descriptorSpec = [
		0 => [ 'file', 'php://stdin', 'r' ],
		1 => [ 'pipe', 'w' ],
		2 => [ 'pipe', 'w' ],
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

	$stdout = stream_get_contents( $pipes[ 1 ] );
	$stderr = stream_get_contents( $pipes[ 2 ] );
	fclose( $pipes[ 1 ] );
	fclose( $pipes[ 2 ] );
	$exitCode = proc_close( $process );

	if ( $stream ) {
		echo $stdout;
		fwrite( STDERR, $stderr );
	}

	return [
		'exit_code' => $exitCode,
		'stdout'    => $stdout,
		'stderr'    => $stderr,
	];
}

/**
 * @return array<int,array{file:string,line:int,column:int,type:string,code:string,message:string,docs:string}>
 */
function wpm_plugin_check_parse_findings( string $output ) :array {
	$findings = [];
	$currentFile = 'unknown';
	foreach ( preg_split( '/\R/', $output ) ?: [] as $line ) {
		$line = trim( $line );
		if ( preg_match( '/^FILE:\s*(.+)$/', $line, $matches ) === 1 ) {
			$currentFile = trim( $matches[ 1 ] );
			continue;
		}

		if ( $line === '' || $line[ 0 ] !== '[' ) {
			continue;
		}

		$items = json_decode( $line, true );
		if ( !is_array( $items ) ) {
			continue;
		}

		foreach ( $items as $item ) {
			if ( !is_array( $item ) ) {
				continue;
			}

			$findings[] = [
				'file'    => $currentFile,
				'line'    => isset( $item[ 'line' ] ) ? (int)$item[ 'line' ] : 0,
				'column'  => isset( $item[ 'column' ] ) ? (int)$item[ 'column' ] : 0,
				'type'    => isset( $item[ 'type' ] ) ? (string)$item[ 'type' ] : '',
				'code'    => isset( $item[ 'code' ] ) ? html_entity_decode( (string)$item[ 'code' ], ENT_QUOTES | ENT_HTML5 ) : '',
				'message' => isset( $item[ 'message' ] ) ? html_entity_decode( (string)$item[ 'message' ], ENT_QUOTES | ENT_HTML5 ) : '',
				'docs'    => isset( $item[ 'docs' ] ) ? (string)$item[ 'docs' ] : '',
			];
		}
	}

	return $findings;
}

/**
 * @param array<int,array{type:string}> $findings
 */
function wpm_plugin_check_count_type( array $findings, string $type ) :int {
	return count(
		array_filter(
			$findings,
			static fn( array $finding ) :bool => strtoupper( $finding[ 'type' ] ) === $type
		)
	);
}

/**
 * @param array<int,array{file:string,line:int,column:int,type:string,code:string,message:string,docs:string}> $findings
 */
function wpm_plugin_check_print_findings( array $findings ) :void {
	foreach ( $findings as $finding ) {
		$location = $finding[ 'file' ];
		if ( $finding[ 'line' ] > 0 ) {
			$location .= ':'.$finding[ 'line' ];
			if ( $finding[ 'column' ] > 0 ) {
				$location .= ':'.$finding[ 'column' ];
			}
		}

		echo sprintf(
			"[%s] %s %s - %s\n",
			strtoupper( $finding[ 'type' ] ),
			$location,
			$finding[ 'code' ],
			$finding[ 'message' ]
		);
		if ( $finding[ 'docs' ] !== '' ) {
			echo '      '.$finding[ 'docs' ].PHP_EOL;
		}
	}
}

function wpm_plugin_check_quote_arg( string $arg ) :string {
	return preg_match( '/\s/', $arg ) === 1 ? '"'.$arg.'"' : $arg;
}
