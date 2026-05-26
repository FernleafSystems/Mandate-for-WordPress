<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @phpstan-type ApplicationPasswordRecord array{uuid:string,name:string,created:int,last_used:int}
 */
class ApplicationPasswordRepository {

	/**
	 * @return list<ApplicationPasswordRecord>
	 */
	public function forUser( int $userId ) :array {
		if ( $userId < 1 || !class_exists( '\WP_Application_Passwords' ) ) {
			return [];
		}

		$passwords = \WP_Application_Passwords::get_user_application_passwords( $userId );
		if ( !is_array( $passwords ) ) {
			return [];
		}

		$records = [];
		foreach ( $passwords as $password ) {
			if ( !is_array( $password ) ) {
				continue;
			}

			$record = $this->normalizePasswordRecord( $password );
			if ( $record !== null ) {
				$records[] = $record;
			}
		}

		return $records;
	}

	/**
	 * @return ApplicationPasswordRecord|null
	 */
	public function findForUser( int $userId, string $uuid ) :?array {
		$uuid = self::normalizeUuid( $uuid );
		if ( $uuid === '' ) {
			return null;
		}

		foreach ( $this->forUser( $userId ) as $password ) {
			if ( $password[ 'uuid' ] === $uuid ) {
				return $password;
			}
		}

		return null;
	}

	public function userOwnsPassword( int $userId, string $uuid ) :bool {
		return $this->findForUser( $userId, $uuid ) !== null;
	}

	/**
	 * @param array<string,mixed> $password
	 * @return ApplicationPasswordRecord|null
	 */
	private function normalizePasswordRecord( array $password ) :?array {
		$uuid = self::normalizeUuid( $this->stringField( $password, 'uuid' ) );
		if ( $uuid === '' ) {
			return null;
		}

		$name = $this->stringField( $password, 'name' );

		return [
			'uuid'      => $uuid,
			'name'      => $name === '' ? $uuid : $name,
			'created'   => $this->timestampField( $password, 'created' ),
			'last_used' => $this->timestampField( $password, 'last_used' ),
		];
	}

	/**
	 * @param array<string,mixed> $source
	 */
	private function stringField( array $source, string $key ) :string {
		$value = $source[ $key ] ?? '';
		return is_scalar( $value ) ? (string)$value : '';
	}

	/**
	 * @param array<string,mixed> $source
	 */
	private function timestampField( array $source, string $key ) :int {
		$value = $source[ $key ] ?? 0;
		return is_numeric( $value ) ? max( 0, (int)$value ) : 0;
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
