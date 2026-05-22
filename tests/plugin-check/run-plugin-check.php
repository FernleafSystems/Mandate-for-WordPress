<?php

declare( strict_types=1 );

const APS_PLUGIN_CHECK_DEFAULT_VERSION = '1.9.0';

$rootDir = dirname( __DIR__, 2 );
$args = array_slice( $_SERVER[ 'argv' ] ?? [], 1 );
$mode = in_array( '--clean', $args, true ) ? 'clean' : 'warm';
$pluginCheckVersion = getenv( 'APS_PLUGIN_CHECK_VERSION' ) ?: APS_PLUGIN_CHECK_DEFAULT_VERSION;
$runtimeDir = $rootDir.'/tests/docker/.runtime/plugin-check';
$packageDir = $runtimeDir.'/application-password-scoper';

$compose = [
	'docker',
	'compose',
	'-p',
	'application-password-scoper-plugin-check',
	'-f',
	'tests/docker/docker-compose.plugin-check.yml',
];

if ( $mode === 'clean' ) {
	aps_plugin_check_run( array_merge( $compose, [ 'down', '-v', '--remove-orphans' ] ), $rootDir );
}

aps_plugin_check_build_package( $rootDir, $runtimeDir, $packageDir );

aps_plugin_check_run( array_merge( $compose, [ 'up', '-d', 'db' ] ), $rootDir );
aps_plugin_check_run(
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
			'if [ ! -f /var/www/html/wp-includes/version.php ]; then cp -a /wordpress-src/. /var/www/html/; fi && mkdir -p /var/www/html/wp-content/plugins',
		]
	),
	$rootDir
);
aps_plugin_check_run(
	array_merge(
		$compose,
		[
			'run',
			'--rm',
			'-T',
			'--env',
			'APS_PLUGIN_CHECK_VERSION='.$pluginCheckVersion,
			'wp-cli',
			'sh',
			'/app/tests/plugin-check/provision-site.sh',
		]
	),
	$rootDir
);

$result = aps_plugin_check_run_capture(
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
			'application-password-scoper',
			'--format=json',
			'--require=./wp-content/plugins/plugin-check/cli.php',
			'--slug=application-password-scoper',
			'--allow-root',
		]
	),
	$rootDir
);

if ( $result[ 'exit_code' ] !== 0 ) {
	echo $result[ 'stdout' ];
	fwrite( STDERR, $result[ 'stderr' ] );
	exit( $result[ 'exit_code' ] );
}

$findings = aps_plugin_check_parse_findings( $result[ 'stdout' ] );
$errorCount = aps_plugin_check_count_type( $findings, 'ERROR' );
$warningCount = aps_plugin_check_count_type( $findings, 'WARNING' );

aps_plugin_check_print_findings( $findings );
echo sprintf(
	"Plugin Check completed with %d error%s and %d warning%s.\n",
	$errorCount,
	$errorCount === 1 ? '' : 's',
	$warningCount,
	$warningCount === 1 ? '' : 's'
);

exit( $errorCount > 0 ? 1 : 0 );

function aps_plugin_check_build_package( string $rootDir, string $runtimeDir, string $packageDir ) :void {
	if ( !is_file( $rootDir.'/vendor/autoload.php' ) ) {
		fwrite( STDERR, "Missing vendor/autoload.php. Run composer dump-autoload before Plugin Check.\n" );
		exit( 1 );
	}

	if ( !is_file( $rootDir.'/readme.txt' ) ) {
		fwrite( STDERR, "Missing readme.txt. Plugin Check runs against the production package shape.\n" );
		exit( 1 );
	}

	if ( !is_dir( $runtimeDir ) && !mkdir( $runtimeDir, 0777, true ) && !is_dir( $runtimeDir ) ) {
		throw new RuntimeException( 'Failed to create Plugin Check runtime directory.' );
	}

	aps_plugin_check_remove_directory( $packageDir, $runtimeDir );
	if ( !mkdir( $packageDir, 0777, true ) && !is_dir( $packageDir ) ) {
		throw new RuntimeException( 'Failed to create Plugin Check package directory.' );
	}

	foreach ( [ 'plugin.php', 'init.php', 'unsupported.php', 'readme.txt', 'composer.json' ] as $file ) {
		aps_plugin_check_copy_file( $rootDir.'/'.$file, $packageDir.'/'.$file );
	}

	foreach ( [ 'assets/dist/admin-page.css', 'assets/dist/admin-page.js' ] as $file ) {
		if ( !is_file( $rootDir.'/'.$file ) ) {
			fwrite( STDERR, "Missing built admin asset: {$file}. Run npm run build before Plugin Check.\n" );
			exit( 1 );
		}
	}

	aps_plugin_check_copy_directory( $rootDir.'/assets/dist', $packageDir.'/assets/dist' );
	aps_plugin_check_copy_directory( $rootDir.'/src', $packageDir.'/src' );
	aps_plugin_check_copy_directory( $rootDir.'/vendor', $packageDir.'/vendor' );
}

function aps_plugin_check_remove_directory( string $directory, string $allowedRoot ) :void {
	if ( !is_dir( $directory ) ) {
		return;
	}

	$realDirectory = realpath( $directory );
	$realAllowedRoot = realpath( $allowedRoot );
	if ( $realDirectory === false || $realAllowedRoot === false ) {
		throw new RuntimeException( 'Could not resolve Plugin Check runtime path.' );
	}

	$realDirectory = aps_plugin_check_normalize_path( $realDirectory );
	$realAllowedRoot = aps_plugin_check_normalize_path( $realAllowedRoot );
	if ( !str_starts_with( $realDirectory, $realAllowedRoot.'/' ) ) {
		throw new RuntimeException( 'Refusing to remove a directory outside the Plugin Check runtime path.' );
	}

	$items = scandir( $directory );
	if ( $items === false ) {
		throw new RuntimeException( 'Could not read Plugin Check runtime directory.' );
	}

	foreach ( $items as $item ) {
		if ( $item === '.' || $item === '..' ) {
			continue;
		}

		$path = $directory.'/'.$item;
		if ( is_dir( $path ) && !is_link( $path ) ) {
			aps_plugin_check_remove_directory( $path, $allowedRoot );
		}
		elseif ( file_exists( $path ) || is_link( $path ) ) {
			unlink( $path );
		}
	}

	if ( is_dir( $directory ) ) {
		rmdir( $directory );
	}
}

function aps_plugin_check_copy_directory( string $source, string $target ) :void {
	if ( !is_dir( $source ) ) {
		throw new RuntimeException( 'Missing package source directory: '.$source );
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach ( $iterator as $item ) {
		$relativePath = substr( $item->getPathname(), strlen( $source ) + 1 );
		$destination = $target.'/'.str_replace( '\\', '/', $relativePath );
		if ( $item->isDir() ) {
			if ( !is_dir( $destination ) && !mkdir( $destination, 0777, true ) && !is_dir( $destination ) ) {
				throw new RuntimeException( 'Failed to create package directory: '.$destination );
			}
			continue;
		}

		aps_plugin_check_copy_file( $item->getPathname(), $destination );
	}
}

function aps_plugin_check_copy_file( string $source, string $target ) :void {
	if ( !is_file( $source ) ) {
		throw new RuntimeException( 'Missing package source file: '.$source );
	}

	$directory = dirname( $target );
	if ( !is_dir( $directory ) && !mkdir( $directory, 0777, true ) && !is_dir( $directory ) ) {
		throw new RuntimeException( 'Failed to create package directory: '.$directory );
	}

	if ( !copy( $source, $target ) ) {
		throw new RuntimeException( 'Failed to copy package file: '.$source );
	}
}

/**
 * @param string[] $command
 * @param array<string,string> $env
 */
function aps_plugin_check_run( array $command, string $cwd, array $env = [] ) :void {
	$result = aps_plugin_check_run_capture( $command, $cwd, $env, true );
	if ( $result[ 'exit_code' ] !== 0 ) {
		exit( $result[ 'exit_code' ] );
	}
}

/**
 * @param string[] $command
 * @param array<string,string> $env
 * @return array{exit_code:int,stdout:string,stderr:string}
 */
function aps_plugin_check_run_capture( array $command, string $cwd, array $env = [], bool $stream = false ) :array {
	echo '> '.implode( ' ', array_map( 'aps_plugin_check_quote_arg', $command ) ).PHP_EOL;
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
function aps_plugin_check_parse_findings( string $output ) :array {
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
function aps_plugin_check_count_type( array $findings, string $type ) :int {
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
function aps_plugin_check_print_findings( array $findings ) :void {
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

function aps_plugin_check_quote_arg( string $arg ) :string {
	return preg_match( '/\s/', $arg ) === 1 ? '"'.$arg.'"' : $arg;
}

function aps_plugin_check_normalize_path( string $path ) :string {
	return str_replace( '\\', '/', $path );
}
