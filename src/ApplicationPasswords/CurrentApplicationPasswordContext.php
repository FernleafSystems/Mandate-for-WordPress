<?php

declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\ApplicationPasswords;

class CurrentApplicationPasswordContext {

	private ?int $userId = null;

	private ?string $uuid = null;

	public function registerHooks() :void {
		add_action( 'application_password_did_authenticate', [ $this, 'captureAuthenticatedPassword' ], 20, 2 );
	}

	/**
	 * @param mixed $user
	 * @param array<string,mixed> $item
	 */
	public function captureAuthenticatedPassword( mixed $user, array $item ) :void {
		$userId = is_object( $user ) && isset( $user->ID ) ? (int)$user->ID : 0;
		$uuid = isset( $item[ 'uuid' ] ) ? ApplicationPasswordRepository::normalizeUuid( (string)$item[ 'uuid' ] ) : '';
		if ( $userId > 0 && $uuid !== '' ) {
			$this->setContext( $userId, $uuid );
		}
	}

	public function setContext( int $userId, string $uuid ) :void {
		$uuid = ApplicationPasswordRepository::normalizeUuid( $uuid );
		$this->userId = $userId > 0 ? $userId : null;
		$this->uuid = $uuid !== '' ? $uuid : null;
	}

	public function userId() :?int {
		if ( $this->userId !== null ) {
			return $this->userId;
		}

		if ( function_exists( 'get_current_user_id' ) ) {
			$userId = (int)get_current_user_id();
			return $userId > 0 ? $userId : null;
		}

		return null;
	}

	public function uuid() :?string {
		if ( $this->uuid !== null ) {
			return $this->uuid;
		}

		if ( function_exists( 'rest_get_authenticated_app_password' ) ) {
			$uuid = rest_get_authenticated_app_password();
			if ( is_string( $uuid ) ) {
				$uuid = ApplicationPasswordRepository::normalizeUuid( $uuid );
				return $uuid !== '' ? $uuid : null;
			}
		}

		return null;
	}
}
