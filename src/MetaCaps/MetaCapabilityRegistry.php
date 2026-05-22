<?php

declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\MetaCaps;

use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Capabilities\CapabilityName;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class MetaCapabilityRegistry {

	private const DEFAULT_META_CAPABILITIES = [
		'edit_post',
		'delete_post',
		'read_post',
		'edit_page',
		'delete_page',
		'read_page',
		'edit_user',
		'delete_user',
		'edit_term',
		'delete_term',
		'assign_term',
	];

	/**
	 * @return array<string,true>
	 */
	public function registered() :array {
		$capabilities = self::DEFAULT_META_CAPABILITIES;
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'application_password_scoper_meta_capabilities', $capabilities );
			if ( is_array( $filtered ) ) {
				$capabilities = $filtered;
			}
		}

		return CapabilityName::normalizeMap( $capabilities );
	}

	public function isRegistered( string $capability ) :bool {
		$capability = CapabilityName::normalize( $capability );
		return $capability !== '' && isset( $this->registered()[ $capability ] );
	}

	/**
	 * @param array<int|string,mixed> $submitted
	 * @return array<string,true>
	 */
	public function intersectSubmitted( array $submitted ) :array {
		return array_intersect_key( CapabilityName::normalizeMap( $submitted ), $this->registered() );
	}
}
