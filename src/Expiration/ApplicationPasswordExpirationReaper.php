<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Expiration;

use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Capabilities\ScopeRepository;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\PluginIdentity;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class ApplicationPasswordExpirationReaper {

	public const HOOK = PluginIdentity::MACHINE_PREFIX.'revoke_expired_application_passwords';

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
		if ( wp_next_scheduled( self::HOOK ) !== false ) {
			return;
		}

		wp_schedule_event( time() + 3600, 'daily', self::HOOK );
	}

	public static function clearScheduledHook() :void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public function revokeExpiredApplicationPasswords() :void {
		foreach ( $this->scopeRepository->all() as $uuid => $record ) {
			if ( !$this->expirationDatePolicy->isExpired( $record[ 'expires_on' ] ) ) {
				continue;
			}

			\WP_Application_Passwords::delete_application_password( $record[ 'user_id' ], $uuid );
		}
	}
}
