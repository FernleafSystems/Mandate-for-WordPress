<?php

declare( strict_types=1 );

$apsTestAutoload = dirname( __DIR__, 2 ).'/vendor/autoload.php';
if ( !is_file( $apsTestAutoload ) ) {
	throw new RuntimeException( 'Composer autoload files are missing. Run composer dump-autoload before running tests.' );
}

require_once $apsTestAutoload;

final class Aps_Test_Roles {

	/**
	 * @param array<string,array<string,bool>> $roles
	 */
	public function __construct( public array $roles ) {
	}

	public function get_role( string $role ) :?object {
		return isset( $this->roles[ $role ] ) ? (object)[ 'capabilities' => $this->roles[ $role ] ] : null;
	}
}

abstract class Aps_Test_Case {

	public function setUp() :void {
		aps_test_reset_state();
	}

	protected function assertSame( mixed $expected, mixed $actual, string $message = '' ) :void {
		if ( $expected !== $actual ) {
			throw new RuntimeException(
				( $message !== '' ? $message."\n" : '' )
				.'Expected: '.var_export( $expected, true )."\n"
				.'Actual: '.var_export( $actual, true )
			);
		}
	}

	protected function assertTrue( mixed $actual, string $message = '' ) :void {
		$this->assertSame( true, $actual, $message );
	}

	protected function assertFalse( mixed $actual, string $message = '' ) :void {
		$this->assertSame( false, $actual, $message );
	}

	protected function assertArrayHasKey( string $key, array $array, string $message = '' ) :void {
		if ( !array_key_exists( $key, $array ) ) {
			throw new RuntimeException( $message !== '' ? $message : 'Missing array key: '.$key );
		}
	}

	protected function assertArrayNotHasKey( string $key, array $array, string $message = '' ) :void {
		if ( array_key_exists( $key, $array ) ) {
			throw new RuntimeException( $message !== '' ? $message : 'Unexpected array key: '.$key );
		}
	}
}

function aps_test_reset_state() :void {
	$GLOBALS[ 'aps_test_options' ] = [];
	$GLOBALS[ 'aps_test_autoload' ] = [];
	$GLOBALS[ 'aps_test_roles' ] = new Aps_Test_Roles( [] );
	$GLOBALS[ 'aps_test_users' ] = [];
	$GLOBALS[ 'aps_test_current_user_id' ] = 1;
	$GLOBALS[ 'aps_test_rest_uuid' ] = null;
	$GLOBALS[ 'aps_test_is_multisite' ] = false;
	$GLOBALS[ 'aps_test_super_admins' ] = [];
}

if ( !function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ) :string {
		return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', $key ) ?? '' );
	}
}

if ( !function_exists( 'wp_is_uuid' ) ) {
	function wp_is_uuid( mixed $uuid, mixed $version = null ) :bool {
		return is_string( $uuid )
			&& preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid ) === 1;
	}
}

if ( !function_exists( 'get_option' ) ) {
	function get_option( string $name, mixed $default = false ) :mixed {
		return $GLOBALS[ 'aps_test_options' ][ $name ] ?? $default;
	}
}

if ( !function_exists( 'update_option' ) ) {
	function update_option( string $name, mixed $value, mixed $autoload = null ) :bool {
		$changed = !array_key_exists( $name, $GLOBALS[ 'aps_test_options' ] ) || $GLOBALS[ 'aps_test_options' ][ $name ] !== $value;
		$GLOBALS[ 'aps_test_options' ][ $name ] = $value;
		$GLOBALS[ 'aps_test_autoload' ][ $name ] = $autoload;
		return $changed;
	}
}

if ( !function_exists( 'wp_roles' ) ) {
	function wp_roles() :Aps_Test_Roles {
		return $GLOBALS[ 'aps_test_roles' ];
	}
}

if ( !function_exists( 'get_userdata' ) ) {
	function get_userdata( int $userId ) :object|false {
		return $GLOBALS[ 'aps_test_users' ][ $userId ] ?? false;
	}
}

if ( !function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() :int {
		return (int)( $GLOBALS[ 'aps_test_current_user_id' ] ?? 0 );
	}
}

if ( !function_exists( 'rest_get_authenticated_app_password' ) ) {
	function rest_get_authenticated_app_password() :?string {
		return $GLOBALS[ 'aps_test_rest_uuid' ];
	}
}

if ( !function_exists( 'apply_filters' ) ) {
	function apply_filters( string $tag, mixed $value, mixed ...$args ) :mixed {
		return $value;
	}
}

if ( !function_exists( 'add_action' ) ) {
	function add_action( string $hookName, mixed $callback, int $priority = 10, int $acceptedArgs = 1 ) :void {
	}
}

if ( !function_exists( 'add_filter' ) ) {
	function add_filter( string $hookName, mixed $callback, int $priority = 10, int $acceptedArgs = 1 ) :void {
	}
}

if ( !function_exists( 'is_multisite' ) ) {
	function is_multisite() :bool {
		return (bool)( $GLOBALS[ 'aps_test_is_multisite' ] ?? false );
	}
}

if ( !function_exists( 'is_super_admin' ) ) {
	function is_super_admin( int $userId = 0 ) :bool {
		return in_array( $userId, $GLOBALS[ 'aps_test_super_admins' ] ?? [], true );
	}
}

aps_test_reset_state();
