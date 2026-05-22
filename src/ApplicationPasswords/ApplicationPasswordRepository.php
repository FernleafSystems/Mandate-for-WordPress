<?php

declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\ApplicationPasswords;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class ApplicationPasswordRepository {

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function forUser( int $userId ) :array {
		if ( $userId < 1 || !class_exists( '\WP_Application_Passwords' ) ) {
			return [];
		}

		$passwords = \WP_Application_Passwords::get_user_application_passwords( $userId );
		return is_array( $passwords ) ? array_values( $passwords ) : [];
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function findForUser( int $userId, string $uuid ) :?array {
		$uuid = self::normalizeUuid( $uuid );
		if ( $uuid === '' ) {
			return null;
		}

		foreach ( $this->forUser( $userId ) as $password ) {
			if ( isset( $password[ 'uuid' ] ) && self::normalizeUuid( (string)$password[ 'uuid' ] ) === $uuid ) {
				return $password;
			}
		}

		return null;
	}

	public function userOwnsPassword( int $userId, string $uuid ) :bool {
		return $this->findForUser( $userId, $uuid ) !== null;
	}

	public static function normalizeUuid( string $uuid ) :string {
		$uuid = strtolower( trim( $uuid ) );
		if ( function_exists( 'wp_is_uuid' ) ) {
			return wp_is_uuid( $uuid ) ? $uuid : '';
		}

		return preg_match(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
			$uuid
		) === 1 ? $uuid : '';
	}
}
