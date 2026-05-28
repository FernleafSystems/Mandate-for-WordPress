<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Admin;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

final class AdminRequest {

	public function method() :string {
		return strtoupper( sanitize_key( $this->serverScalar( 'REQUEST_METHOD' ) ) );
	}

	public function getScalar( string $key ) :string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET selection state is read-only and sanitized here.
		return $this->scalarFrom( $_GET, $key );
	}

	public function postScalar( string $key ) :string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Action-specific nonces are verified before mutations; this adapter sanitizes reads.
		return $this->scalarFrom( $_POST, $key );
	}

	public function hasPostKey( string $key ) :bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Presence check only; action-specific nonces are verified before mutations.
		return array_key_exists( $key, $_POST );
	}

	/**
	 * @return string[]
	 */
	public function postScalarList( string $key ) :array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized item-by-item below after unslashing the submitted list.
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : [];
		if ( !is_array( $value ) ) {
			return [];
		}

		$items = [];
		foreach ( $value as $item ) {
			if ( is_scalar( $item ) ) {
				$items[] = sanitize_text_field( $item );
			}
		}

		return $items;
	}

	public function isOwnProfileScreen() :bool {
		return $this->adminScriptName() === 'profile.php';
	}

	private function serverScalar( string $key ) :string {
		return $this->scalarFrom( $_SERVER, $key );
	}

	private function adminScriptName() :string {
		foreach ( [ 'SCRIPT_NAME', 'PHP_SELF', 'REQUEST_URI' ] as $key ) {
			$value = $this->serverScalar( $key );
			if ( $value === '' ) {
				continue;
			}

			$path = wp_parse_url( $value, PHP_URL_PATH );
			if ( is_string( $path ) && $path !== '' ) {
				return basename( $path );
			}
		}

		return '';
	}

	/**
	 * @param array<string,mixed> $source
	 */
	private function scalarFrom( array $source, string $key ) :string {
		$value = $source[ $key ] ?? '';
		return is_scalar( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : '';
	}
}
