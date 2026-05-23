<?php

declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ).'/' );
}

$wpmTestAutoload = dirname( __DIR__, 2 ).'/vendor/autoload.php';
if ( !is_file( $wpmTestAutoload ) ) {
	throw new RuntimeException( 'Composer autoload files are missing. Run composer dump-autoload before running tests.' );
}

require_once $wpmTestAutoload;

final class Wpm_Test_Roles {

	/**
	 * @param array<string,array<string,bool>> $roles
	 */
	public function __construct( public array $roles ) {
	}

	public function get_role( string $role ) :?object {
		return isset( $this->roles[ $role ] ) ? (object)[ 'capabilities' => $this->roles[ $role ] ] : null;
	}
}

final class Wpm_Test_Wp_Die_Exception extends RuntimeException {
}

final class Wpm_Test_Redirect_Exception extends RuntimeException {

	public function __construct( public string $location ) {
		parent::__construct( 'Redirected to '.$location );
	}
}

abstract class Wpm_Test_Case {

	public function setUp() :void {
		wpm_test_reset_state();
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

	protected function assertThrowsRuntimeException( callable $callback, string $message = '' ) :void {
		try {
			$callback();
		}
		catch ( RuntimeException ) {
			return;
		}

		throw new RuntimeException( $message !== '' ? $message : 'Expected RuntimeException was not thrown.' );
	}
}

function wpm_test_reset_state() :void {
	$GLOBALS[ 'wpm_test_options' ] = [];
	$GLOBALS[ 'wpm_test_autoload' ] = [];
	$GLOBALS[ 'wpm_test_roles' ] = new Wpm_Test_Roles( [] );
	$GLOBALS[ 'wpm_test_users' ] = [];
	$GLOBALS[ 'wpm_test_current_user_id' ] = 1;
	$GLOBALS[ 'wpm_test_current_user_caps' ] = [ 'manage_options' => true ];
	$GLOBALS[ 'wpm_test_rest_uuid' ] = null;
	$GLOBALS[ 'wpm_test_is_multisite' ] = false;
	$GLOBALS[ 'wpm_test_super_admins' ] = [];
	$GLOBALS[ 'wpm_test_actions' ] = [];
	$GLOBALS[ 'wpm_test_filters' ] = [];
	$GLOBALS[ 'wpm_test_valid_nonces' ] = [];
	$GLOBALS[ 'wpm_test_last_redirect' ] = null;
	$GLOBALS[ 'wpm_test_wp_die' ] = [];
	$_GET = [];
	$_POST = [];
	$_REQUEST = [];
	$_SERVER[ 'REQUEST_METHOD' ] = 'GET';
	if ( class_exists( 'WP_Application_Passwords' ) ) {
		WP_Application_Passwords::$passwordsByUser = [];
	}
}

function wpm_test_set_valid_nonce( string $name, string $action ) :string {
	$nonce = 'nonce:'.$action;
	$GLOBALS[ 'wpm_test_valid_nonces' ][ $name ] = [
		'action' => $action,
		'nonce'  => $nonce,
	];
	return $nonce;
}

if ( !function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ) :string {
		return $text;
	}
}

if ( !function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ) :string {
		return esc_html( $text );
	}
}

if ( !function_exists( 'esc_attr__' ) ) {
	function esc_attr__( string $text, string $domain = 'default' ) :string {
		return esc_attr( $text );
	}
}

if ( !function_exists( 'esc_html' ) ) {
	function esc_html( mixed $text ) :string {
		return htmlspecialchars( (string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( !function_exists( 'esc_attr' ) ) {
	function esc_attr( mixed $text ) :string {
		return htmlspecialchars( (string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( !function_exists( 'esc_url' ) ) {
	function esc_url( mixed $url ) :string {
		return (string)$url;
	}
}

if ( !function_exists( 'wp_unslash' ) ) {
	function wp_unslash( mixed $value ) :mixed {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string)$value );
	}
}

if ( !function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( mixed $value ) :string {
		return trim( strip_tags( (string)$value ) );
	}
}

if ( !function_exists( 'absint' ) ) {
	function absint( mixed $value ) :int {
		return abs( (int)$value );
	}
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
		return $GLOBALS[ 'wpm_test_options' ][ $name ] ?? $default;
	}
}

if ( !function_exists( 'update_option' ) ) {
	function update_option( string $name, mixed $value, mixed $autoload = null ) :bool {
		$changed = !array_key_exists( $name, $GLOBALS[ 'wpm_test_options' ] ) || $GLOBALS[ 'wpm_test_options' ][ $name ] !== $value;
		$GLOBALS[ 'wpm_test_options' ][ $name ] = $value;
		$GLOBALS[ 'wpm_test_autoload' ][ $name ] = $autoload;
		return $changed;
	}
}

if ( !function_exists( 'wp_roles' ) ) {
	function wp_roles() :Wpm_Test_Roles {
		return $GLOBALS[ 'wpm_test_roles' ];
	}
}

if ( !function_exists( 'get_userdata' ) ) {
	function get_userdata( int $userId ) :object|false {
		return $GLOBALS[ 'wpm_test_users' ][ $userId ] ?? false;
	}
}

if ( !function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() :int {
		return (int)( $GLOBALS[ 'wpm_test_current_user_id' ] ?? 0 );
	}
}

if ( !function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability, mixed ...$args ) :bool {
		return !empty( $GLOBALS[ 'wpm_test_current_user_caps' ][ $capability ] );
	}
}

if ( !function_exists( 'wp_die' ) ) {
	function wp_die( mixed $message = '' ) :never {
		$GLOBALS[ 'wpm_test_wp_die' ][] = (string)$message;
		throw new Wpm_Test_Wp_Die_Exception( (string)$message );
	}
}

if ( !function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ) :string {
		return 'https://example.test/wp-admin/'.ltrim( $path, '/' );
	}
}

if ( !function_exists( 'add_query_arg' ) ) {
	function add_query_arg( array $args, string $url ) :string {
		$separator = str_contains( $url, '?' ) ? '&' : '?';
		return $url.$separator.http_build_query( $args, '', '&' );
	}
}

if ( !function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( string $location, int $status = 302, string $xRedirectBy = 'WordPress' ) :bool {
		$GLOBALS[ 'wpm_test_last_redirect' ] = $location;
		throw new Wpm_Test_Redirect_Exception( $location );
	}
}

if ( !function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( string $action = '-1', string $name = '_wpnonce', bool $referer = true, bool $display = true ) :string {
		$nonce = wpm_test_set_valid_nonce( $name, $action );
		$field = '<input type="hidden" name="'.esc_attr( $name ).'" value="'.esc_attr( $nonce ).'" />';
		if ( $display ) {
			echo $field;
		}
		return $field;
	}
}

if ( !function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( string $action = '-1', string $query_arg = '_wpnonce' ) :int|false {
		$submitted = $_POST[ $query_arg ] ?? $_GET[ $query_arg ] ?? '';
		$valid = $GLOBALS[ 'wpm_test_valid_nonces' ][ $query_arg ] ?? null;
		if ( is_array( $valid )
			&& ( $valid[ 'action' ] ?? null ) === $action
			&& ( $valid[ 'nonce' ] ?? null ) === $submitted
		) {
			return 1;
		}

		wp_die( 'invalid nonce' );
	}
}

if ( !function_exists( 'rest_get_authenticated_app_password' ) ) {
	function rest_get_authenticated_app_password() :?string {
		return $GLOBALS[ 'wpm_test_rest_uuid' ];
	}
}

if ( !function_exists( 'apply_filters' ) ) {
	function apply_filters( string $tag, mixed $value, mixed ...$args ) :mixed {
		$callbacks = $GLOBALS[ 'wpm_test_filters' ][ $tag ] ?? [];
		if ( $callbacks === [] ) {
			return $value;
		}

		ksort( $callbacks );
		foreach ( $callbacks as $priorityCallbacks ) {
			foreach ( $priorityCallbacks as $callback ) {
				$acceptedArgs = max( 1, (int)$callback[ 'accepted_args' ] );
				$value = $callback[ 'callback' ]( ...array_slice( [ $value, ...$args ], 0, $acceptedArgs ) );
			}
		}

		return $value;
	}
}

if ( !class_exists( 'WP_Application_Passwords' ) ) {
	final class WP_Application_Passwords {

		/**
		 * @var array<int,mixed>
		 */
		public static array $passwordsByUser = [];

		public static function get_user_application_passwords( int $userId ) :mixed {
			return self::$passwordsByUser[ $userId ] ?? [];
		}
	}
}

if ( !function_exists( 'add_action' ) ) {
	function add_action( string $hookName, mixed $callback, int $priority = 10, int $acceptedArgs = 1 ) :void {
		$GLOBALS[ 'wpm_test_actions' ][ $hookName ][ $priority ][] = [
			'callback'      => $callback,
			'accepted_args' => $acceptedArgs,
		];
	}
}

if ( !function_exists( 'do_action' ) ) {
	function do_action( string $hookName, mixed ...$args ) :void {
		$callbacks = $GLOBALS[ 'wpm_test_actions' ][ $hookName ] ?? [];
		if ( $callbacks === [] ) {
			return;
		}

		ksort( $callbacks );
		foreach ( $callbacks as $priorityCallbacks ) {
			foreach ( $priorityCallbacks as $callback ) {
				$acceptedArgs = max( 0, (int)$callback[ 'accepted_args' ] );
				$callback[ 'callback' ]( ...array_slice( $args, 0, $acceptedArgs ) );
			}
		}
	}
}

if ( !function_exists( 'add_filter' ) ) {
	function add_filter( string $hookName, mixed $callback, int $priority = 10, int $acceptedArgs = 1 ) :void {
		$GLOBALS[ 'wpm_test_filters' ][ $hookName ][ $priority ][] = [
			'callback'      => $callback,
			'accepted_args' => $acceptedArgs,
		];
	}
}

if ( !function_exists( 'selected' ) ) {
	function selected( mixed $selected, mixed $current = true, bool $display = true ) :string {
		$result = (string)$selected === (string)$current ? 'selected="selected"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}

if ( !function_exists( 'checked' ) ) {
	function checked( mixed $checked, mixed $current = true, bool $display = true ) :string {
		$result = (bool)$checked === (bool)$current ? 'checked="checked"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}

if ( !function_exists( 'disabled' ) ) {
	function disabled( mixed $disabled, mixed $current = true, bool $display = true ) :string {
		$result = (bool)$disabled === (bool)$current ? 'disabled="disabled"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}

if ( !function_exists( 'wp_dropdown_users' ) ) {
	function wp_dropdown_users( array $args = [] ) :string {
		$name = isset( $args[ 'name' ] ) ? (string)$args[ 'name' ] : 'user';
		$id = isset( $args[ 'id' ] ) ? (string)$args[ 'id' ] : $name;
		$selectedUser = isset( $args[ 'selected' ] ) ? (string)$args[ 'selected' ] : '';
		$output = '<select name="'.esc_attr( $name ).'" id="'.esc_attr( $id ).'">'
			.'<option value="'.esc_attr( $selectedUser ).'" selected="selected">'.esc_html( $selectedUser ).'</option>'
			.'</select>';
		echo $output;
		return $output;
	}
}

if ( !function_exists( 'is_multisite' ) ) {
	function is_multisite() :bool {
		return (bool)( $GLOBALS[ 'wpm_test_is_multisite' ] ?? false );
	}
}

if ( !function_exists( 'is_super_admin' ) ) {
	function is_super_admin( int $userId = 0 ) :bool {
		return in_array( $userId, $GLOBALS[ 'wpm_test_super_admins' ] ?? [], true );
	}
}

wpm_test_reset_state();
