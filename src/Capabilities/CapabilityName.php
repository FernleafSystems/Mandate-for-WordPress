<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Capabilities;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class CapabilityName {

	public static function normalize( string $capability ) :string {
		$capability = trim( $capability );
		if ( $capability === '' ) {
			return '';
		}

		$capability = sanitize_key( $capability );

		return preg_match( '/^[a-z0-9_\-]+$/', $capability ) === 1 ? $capability : '';
	}

	/**
	 * @param array<int|string,mixed> $capabilities
	 * @return array<string,true>
	 */
	public static function normalizeMap( array $capabilities ) :array {
		$normalized = [];
		foreach ( $capabilities as $key => $value ) {
			if ( is_int( $key ) ) {
				$name = is_scalar( $value ) ? (string)$value : '';
				$granted = true;
			}
			else {
				$name = (string)$key;
				$granted = (bool)$value;
			}

			$name = self::normalize( $name );
			if ( $name !== '' && $granted ) {
				$normalized[ $name ] = true;
			}
		}

		ksort( $normalized, SORT_NATURAL );
		return $normalized;
	}
}
