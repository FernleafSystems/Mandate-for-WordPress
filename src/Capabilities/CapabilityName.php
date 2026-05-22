<?php

declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Capabilities;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class CapabilityName {

	public static function normalize( string $capability ) :string {
		$capability = trim( $capability );
		if ( $capability === '' ) {
			return '';
		}

		if ( function_exists( 'sanitize_key' ) ) {
			$capability = sanitize_key( $capability );
		}
		else {
			$capability = strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', $capability ) ?? '' );
		}

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
