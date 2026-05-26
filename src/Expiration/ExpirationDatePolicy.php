<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Expiration;

class ExpirationDatePolicy {

	public function normalize( mixed $date ) :?string {
		if ( !is_scalar( $date ) ) {
			return null;
		}

		$date = trim( (string)$date );
		if ( $date === '' || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) !== 1 ) {
			return null;
		}

		[ $year, $month, $day ] = array_map( 'intval', explode( '-', $date ) );
		return checkdate( $month, $day, $year ) ? $date : null;
	}

	public function isExpired( ?string $expiresOn ) :bool {
		$expiresOn = $this->normalize( $expiresOn );
		return $expiresOn !== null && $expiresOn < $this->today();
	}

	public function today() :string {
		return function_exists( 'wp_date' ) ? wp_date( 'Y-m-d' ) : gmdate( 'Y-m-d' );
	}
}
