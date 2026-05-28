<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Admin;

use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\ApplicationPasswords\ApplicationPasswordRepository;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\PluginIdentity;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class AdminScopeFormSecurity {

	public const ACTION_SAVE = 'save_scope';
	public const ACTION_CLEAR = 'clear_scope';
	public const FORM_ACTIONS = [
		self::ACTION_SAVE  => true,
		self::ACTION_CLEAR => true,
	];
	public const ACTION_FIELD = PluginIdentity::MACHINE_PREFIX.'action';

	private const NONCE_ACTION_PREFIX = PluginIdentity::MACHINE_PREFIX.'scope';

	public function isSupportedAction( string $action ) :bool {
		return isset( self::FORM_ACTIONS[ $action ] );
	}

	public function nonceAction( string $action, int $userId, string $uuid ) :string {
		$this->assertSupportedAction( $action );

		return implode(
			':',
			[
				self::NONCE_ACTION_PREFIX,
				$action,
				(string)max( 0, $userId ),
				ApplicationPasswordRepository::normalizeUuid( $uuid ),
			]
		);
	}

	public function nonceName( string $action ) :string {
		$this->assertSupportedAction( $action );

		return match ( $action ) {
			self::ACTION_SAVE => PluginIdentity::MACHINE_PREFIX.'save_scope_nonce',
			self::ACTION_CLEAR => PluginIdentity::MACHINE_PREFIX.'clear_scope_nonce',
		};
	}

	public function nonceFields( int $userId, string $uuid ) :string {
		return sprintf(
			'<input type="hidden" name="%1$s" value="%2$s" /><input type="hidden" name="%3$s" value="%4$s" />',
			esc_attr( $this->nonceName( self::ACTION_SAVE ) ),
			esc_attr( wp_create_nonce( $this->nonceAction( self::ACTION_SAVE, $userId, $uuid ) ) ),
			esc_attr( $this->nonceName( self::ACTION_CLEAR ) ),
			esc_attr( wp_create_nonce( $this->nonceAction( self::ACTION_CLEAR, $userId, $uuid ) ) )
		);
	}

	private function assertSupportedAction( string $action ) :void {
		if ( !$this->isSupportedAction( $action ) ) {
			throw new \InvalidArgumentException( 'Unsupported scope form action.' );
		}
	}
}
