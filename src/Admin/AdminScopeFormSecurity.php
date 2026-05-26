<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Admin;

use FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords\ApplicationPasswordRepository;

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

	private const NONCE_ACTION_PREFIX = 'mandate_scope';

	private AdminTrustedHtmlSanitizer $trustedHtmlSanitizer;

	public function __construct( AdminTrustedHtmlSanitizer $trustedHtmlSanitizer ) {
		$this->trustedHtmlSanitizer = $trustedHtmlSanitizer;
	}

	public function isSupportedAction( string $action ) :bool {
		return isset( self::FORM_ACTIONS[ $action ] );
	}

	public function nonceAction( string $action, int $userId, string $uuid ) :string {
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
		return 'mandate_'.$action.'_nonce';
	}

	public function nonceFields( int $userId, string $uuid ) :string {
		return $this->trustedHtmlSanitizer->nonceFields( wp_nonce_field(
			$this->nonceAction( self::ACTION_SAVE, $userId, $uuid ),
			$this->nonceName( self::ACTION_SAVE ),
			false,
			false
		).wp_nonce_field(
			$this->nonceAction( self::ACTION_CLEAR, $userId, $uuid ),
			$this->nonceName( self::ACTION_CLEAR ),
			false,
			false
		) );
	}
}
