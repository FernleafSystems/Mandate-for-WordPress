<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\MandateAppSecurity\MetaCaps;

use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Capabilities\CapabilityName;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\PluginIdentity;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class MetaCapabilityRegistry {

	public const FILTER_META_CAPABILITIES = PluginIdentity::MACHINE_PREFIX.'meta_capabilities';

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
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Constant resolves to mdpsc_meta_capabilities through PluginIdentity.
		$filtered = apply_filters( self::FILTER_META_CAPABILITIES, $capabilities );
		if ( is_array( $filtered ) ) {
			$capabilities = $filtered;
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
