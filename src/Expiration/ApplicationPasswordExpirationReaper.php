<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Expiration;

use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\ScopeRepository;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class ApplicationPasswordExpirationReaper {

	public const HOOK = 'mandate_revoke_expired_application_passwords';

	private ScopeRepository $scopeRepository;

	private ExpirationDatePolicy $expirationDatePolicy;

	public function __construct(
		ScopeRepository $scopeRepository,
		ExpirationDatePolicy $expirationDatePolicy
	) {
		$this->scopeRepository = $scopeRepository;
		$this->expirationDatePolicy = $expirationDatePolicy;
	}

	public function registerHooks() :void {
		add_action( self::HOOK, [ $this, 'revokeExpiredApplicationPasswords' ] );
		$this->ensureScheduled();
	}

	public function ensureScheduled() :void {
		self::ensureScheduledHook();
	}

	public static function ensureScheduledHook() :void {
		if ( !function_exists( 'wp_next_scheduled' ) || !function_exists( 'wp_schedule_event' ) ) {
			return;
		}

		if ( wp_next_scheduled( self::HOOK ) !== false ) {
			return;
		}

		wp_schedule_event( time() + 3600, 'daily', self::HOOK );
	}

	public static function clearScheduledHook() :void {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::HOOK );
			return;
		}

		if ( !function_exists( 'wp_next_scheduled' ) || !function_exists( 'wp_unschedule_event' ) ) {
			return;
		}

		while ( ( $timestamp = wp_next_scheduled( self::HOOK ) ) !== false ) {
			wp_unschedule_event( (int)$timestamp, self::HOOK );
		}
	}

	public function revokeExpiredApplicationPasswords() :void {
		if ( !class_exists( '\WP_Application_Passwords' )
			|| !method_exists( '\WP_Application_Passwords', 'delete_application_password' )
		) {
			return;
		}

		foreach ( $this->scopeRepository->all() as $uuid => $record ) {
			if ( !$this->expirationDatePolicy->isExpired( $record[ 'expires_on' ] ) ) {
				continue;
			}

			\WP_Application_Passwords::delete_application_password( $record[ 'user_id' ], $uuid );
		}
	}
}
