<?php

declare( strict_types=1 );

use Symfony\Component\Filesystem\Path;
use FernleafSystems\Wordpress\Plugin\Mandate\PluginIdentity;

$rootDir = dirname( __DIR__, 2 );
$autoload = $rootDir.'/vendor/autoload.php';
if ( !is_file( $autoload ) ) {
	fwrite( STDERR, 'Missing vendor/autoload.php. Run composer install before integration tests.'.PHP_EOL );
	exit( 1 );
}

require $autoload;

$wpPhpunitDir = $rootDir.'/vendor/wp-phpunit/wp-phpunit';
$wpFunctions = $wpPhpunitDir.'/includes/functions.php';
$wpBootstrap = $wpPhpunitDir.'/includes/bootstrap.php';
if ( !is_file( $wpFunctions ) || !is_file( $wpBootstrap ) ) {
	fwrite( STDERR, 'Missing wp-phpunit files. Run composer install before integration tests.'.PHP_EOL );
	exit( 1 );
}

$polyfillsDir = $rootDir.'/vendor/yoast/phpunit-polyfills';
if ( !is_file( $polyfillsDir.'/phpunitpolyfills-autoload.php' ) ) {
	fwrite( STDERR, 'Missing PHPUnit Polyfills. Run composer install before integration tests.'.PHP_EOL );
	exit( 1 );
}
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $polyfillsDir );

$coreDir = wpm_integration_wp_core_dir( $rootDir );
if ( !wpm_integration_wp_core_valid( $coreDir ) ) {
	fwrite( STDERR, 'WordPress core not found at '.$coreDir.'. Run composer test:integration:install first.'.PHP_EOL );
	exit( 1 );
}

$configPath = wpm_integration_write_wp_tests_config( $coreDir );
putenv( 'WP_PHPUNIT__TESTS_CONFIG='.$configPath );
putenv( 'WP_PHPUNIT__TABLE_PREFIX=wptests_' );

require_once $wpFunctions;

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $rootDir ) :void {
		require_once $rootDir.'/'.PluginIdentity::MAIN_FILE;
	},
	0
);

require_once $wpBootstrap;

function wpm_integration_wp_core_dir( string $rootDir ) :string {
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

function wpm_integration_wp_core_valid( string $coreDir ) :bool {
	return is_file( Path::join( $coreDir, 'wp-load.php' ) )
		&& is_file( Path::join( $coreDir, 'wp-includes/version.php' ) );
}

function wpm_integration_write_wp_tests_config( string $coreDir ) :string {
	$configPath = Path::join( rtrim( sys_get_temp_dir(), "\\/" ), 'mandate-wp-tests-config.php' );
	$coreDir = rtrim( Path::canonicalize( $coreDir ), "\\/" ).'/';
	$dbName = wpm_integration_env( 'WPM_INTEGRATION_DB_NAME', 'wordpress_test_integration' );
	$dbUser = wpm_integration_env( 'WPM_INTEGRATION_DB_USER', 'root' );
	$dbPass = wpm_integration_env( 'WPM_INTEGRATION_DB_PASS', 'testpass' );
	$dbHost = wpm_integration_env( 'WPM_INTEGRATION_DB_HOST', '127.0.0.1:3312' );

	$config = <<<'PHP'
<?php

define( 'ABSPATH', %s );
define( 'DB_NAME', %s );
define( 'DB_USER', %s );
define( 'DB_PASSWORD', %s );
define( 'DB_HOST', %s );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
define( 'WP_TESTS_DOMAIN', 'example.test' );
define( 'WP_TESTS_EMAIL', 'admin@example.test' );
define( 'WP_TESTS_TITLE', 'Mandate Integration Tests' );
define( 'WP_PHP_BINARY', PHP_BINARY );
define( 'WP_DEBUG', true );

$table_prefix = 'wptests_';
PHP;

	file_put_contents(
		$configPath,
		sprintf(
			$config,
			var_export( $coreDir, true ),
			var_export( $dbName, true ),
			var_export( $dbUser, true ),
			var_export( $dbPass, true ),
			var_export( $dbHost, true )
		).PHP_EOL
	);

	return $configPath;
}

function wpm_integration_env( string $name, string $default ) :string {
	$value = getenv( $name );
	return is_string( $value ) && $value !== '' ? $value : $default;
}
