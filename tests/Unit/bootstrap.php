<?php

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

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
	 * @param array<string,string> $role_names
	 */
	public function __construct( public array $roles, public array $role_names = [] ) {
	}

	public function get_role( string $role ) :?object {
		return isset( $this->roles[ $role ] ) ? (object)[ 'capabilities' => $this->roles[ $role ] ] : null;
	}

	/**
	 * @return array<string,string>
	 */
	public function get_names() :array {
		return $this->role_names;
	}
}

final class Wpm_Test_Wp_Die_Exception extends RuntimeException {
}

final class Wpm_Test_Redirect_Exception extends RuntimeException {

	public function __construct( public string $location ) {
		parent::__construct( 'Redirected to '.$location );
	}
}

abstract class Wpm_Test_Case extends TestCase {

	protected function setUp() :void {
		parent::setUp();
		wpm_test_reset_state();
	}

	protected function assertThrowsRuntimeException( callable $callback, string $message = '' ) :void {
		try {
			$callback();
		}
		catch ( RuntimeException ) {
			$this->addToAssertionCount( 1 );
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
	$GLOBALS[ 'wpm_test_now' ] = strtotime( '2026-05-23 12:00:00 UTC' );
	$GLOBALS[ 'wpm_test_is_multisite' ] = false;
	$GLOBALS[ 'wpm_test_super_admins' ] = [];
	$GLOBALS[ 'wpm_test_actions' ] = [];
	$GLOBALS[ 'wpm_test_filters' ] = [];
	$GLOBALS[ 'wpm_test_scheduled_events' ] = [];
	$GLOBALS[ 'wpm_test_deactivation_hooks' ] = [];
	$GLOBALS[ 'wpm_test_management_pages' ] = [];
	$GLOBALS[ 'wpm_test_enqueued_styles' ] = [];
	$GLOBALS[ 'wpm_test_enqueued_scripts' ] = [];
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

if ( !function_exists( 'wp_kses' ) ) {
	function wp_kses( string $html, array $allowed_html, array $allowed_protocols = [] ) :string {
		unset( $allowed_protocols );

		return (string)preg_replace_callback(
			'/<\s*(\/?)\s*([a-z0-9]+)([^>]*)>/i',
			static function ( array $matches ) use ( $allowed_html ) :string {
				$closing = $matches[ 1 ] === '/';
				$tag = strtolower( $matches[ 2 ] );
				if ( !isset( $allowed_html[ $tag ] ) || !is_array( $allowed_html[ $tag ] ) ) {
					return '';
				}
				if ( $closing ) {
					return '</'.$tag.'>';
				}

				$attributes = '';
				preg_match_all(
					'/\s+([a-z0-9_-]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/i',
					$matches[ 3 ],
					$attributeMatches,
					PREG_SET_ORDER
				);
				foreach ( $attributeMatches as $attributeMatch ) {
					$name = strtolower( $attributeMatch[ 1 ] );
					if ( !isset( $allowed_html[ $tag ][ $name ] ) ) {
						continue;
					}

					$value = $attributeMatch[ 2 ] ?? $attributeMatch[ 3 ] ?? $attributeMatch[ 4 ] ?? $name;
					$attributes .= ' '.$name.'="'.esc_attr( $value ).'"';
				}

				return '<'.$tag.$attributes.'>';
			},
			$html
		);
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

if ( !function_exists( 'wp_date' ) ) {
	function wp_date( string $format, ?int $timestamp = null, mixed $timezone = null ) :string {
		return gmdate( $format, $timestamp ?? (int)$GLOBALS[ 'wpm_test_now' ] );
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

if ( !function_exists( 'add_management_page' ) ) {
	function add_management_page(
		string $pageTitle,
		string $menuTitle,
		string $capability,
		string $menuSlug,
		callable $callback
	) :string {
		$hookSuffix = 'tools_page_'.$menuSlug;
		$GLOBALS[ 'wpm_test_management_pages' ][ $menuSlug ] = [
			'page_title'  => $pageTitle,
			'menu_title'  => $menuTitle,
			'capability'  => $capability,
			'menu_slug'   => $menuSlug,
			'callback'    => $callback,
			'hook_suffix' => $hookSuffix,
		];

		return $hookSuffix;
	}
}

if ( !function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( string $file ) :string {
		return rtrim( dirname( $file ), "\\/" ).'/';
	}
}

if ( !function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( string $file ) :string {
		return 'https://example.test/wp-content/plugins/'.basename( dirname( $file ) ).'/';
	}
}

if ( !function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style(
		string $handle,
		string $src = '',
		array $deps = [],
		string|bool|null $ver = false,
		string $media = 'all'
	) :void {
		$GLOBALS[ 'wpm_test_enqueued_styles' ][ $handle ] = [
			'src'   => $src,
			'deps'  => $deps,
			'ver'   => $ver,
			'media' => $media,
		];
	}
}

if ( !function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script(
		string $handle,
		string $src = '',
		array $deps = [],
		string|bool|null $ver = false,
		array|bool $args = []
	) :void {
		$GLOBALS[ 'wpm_test_enqueued_scripts' ][ $handle ] = [
			'src'  => $src,
			'deps' => $deps,
			'ver'  => $ver,
			'args' => $args,
		];
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
		$field = '<input type="hidden" id="'.esc_attr( $name ).'" name="'.esc_attr( $name ).'" value="'.esc_attr( $nonce ).'" />';
		if ( $referer ) {
			$field .= '<input type="hidden" name="_wp_http_referer" value="/wp-admin/tools.php?page=mandate-app-security" />';
		}
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

		public static function delete_application_password( int $userId, string $uuid ) :bool {
			$passwords = self::$passwordsByUser[ $userId ] ?? [];
			if ( !is_array( $passwords ) ) {
				return false;
			}

			foreach ( $passwords as $index => $password ) {
				if ( !is_array( $password ) || ( $password[ 'uuid' ] ?? null ) !== $uuid ) {
					continue;
				}

				unset( self::$passwordsByUser[ $userId ][ $index ] );
				self::$passwordsByUser[ $userId ] = array_values( self::$passwordsByUser[ $userId ] );
				do_action( 'wp_delete_application_password', $userId, $password );
				return true;
			}

			return false;
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

if ( !function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( string $hook, array $args = [] ) :int|false {
		$events = $GLOBALS[ 'wpm_test_scheduled_events' ][ $hook ] ?? [];
		if ( $events === [] ) {
			return false;
		}

		ksort( $events );
		return (int)array_key_first( $events );
	}
}

if ( !function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event(
		int $timestamp,
		string $recurrence,
		string $hook,
		array $args = [],
		bool $wpError = false
	) :bool {
		$GLOBALS[ 'wpm_test_scheduled_events' ][ $hook ][ $timestamp ] = [
			'recurrence' => $recurrence,
			'args'       => $args,
		];
		return true;
	}
}

if ( !function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( string $hook, array $args = [], bool $wpError = false ) :int {
		$count = count( $GLOBALS[ 'wpm_test_scheduled_events' ][ $hook ] ?? [] );
		unset( $GLOBALS[ 'wpm_test_scheduled_events' ][ $hook ] );
		return $count;
	}
}

if ( !function_exists( 'wp_unschedule_event' ) ) {
	function wp_unschedule_event( int $timestamp, string $hook, array $args = [], bool $wpError = false ) :bool {
		unset( $GLOBALS[ 'wpm_test_scheduled_events' ][ $hook ][ $timestamp ] );
		if ( ( $GLOBALS[ 'wpm_test_scheduled_events' ][ $hook ] ?? [] ) === [] ) {
			unset( $GLOBALS[ 'wpm_test_scheduled_events' ][ $hook ] );
		}
		return true;
	}
}

if ( !function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( string $file, callable $callback ) :void {
		$GLOBALS[ 'wpm_test_deactivation_hooks' ][ $file ] = $callback;
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
		if ( (bool)( $args[ 'echo' ] ?? true ) ) {
			echo $output;
		}
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
