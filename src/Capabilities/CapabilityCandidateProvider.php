<?php

declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Capabilities;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class CapabilityCandidateProvider {

	/**
	 * @return array<string,true>
	 */
	public function forUser( int $userId ) :array {
		if ( $userId < 1 || !function_exists( 'get_userdata' ) ) {
			return [];
		}

		$user = get_userdata( $userId );
		if ( !is_object( $user ) ) {
			return [];
		}

		return $this->forUserObject( $user );
	}

	/**
	 * @param object $user
	 * @return array<string,true>
	 */
	public function forUserObject( object $user ) :array {
		$roles = isset( $user->roles ) && is_array( $user->roles ) ? $user->roles : [];
		return $this->forRoles( array_values( array_filter( array_map( 'strval', $roles ) ) ) );
	}

	/**
	 * @param string[] $roles
	 * @return array<string,true>
	 */
	public function forRoles( array $roles ) :array {
		if ( !function_exists( 'wp_roles' ) ) {
			return [];
		}

		$wpRoles = wp_roles();
		$roleCapabilities = [];
		foreach ( $roles as $roleName ) {
			$role = is_object( $wpRoles ) && method_exists( $wpRoles, 'get_role' ) ? $wpRoles->get_role( $roleName ) : null;
			if ( is_object( $role ) && isset( $role->capabilities ) && is_array( $role->capabilities ) ) {
				$roleCapabilities[ $roleName ] = $role->capabilities;
			}
		}

		return $this->fromRoleCapabilities( $roleCapabilities );
	}

	/**
	 * @param array<string,array<int|string,mixed>> $roleCapabilities
	 * @return array<string,true>
	 */
	public function fromRoleCapabilities( array $roleCapabilities ) :array {
		$candidates = [];
		foreach ( $roleCapabilities as $capabilities ) {
			foreach ( CapabilityName::normalizeMap( $capabilities ) as $capability => $granted ) {
				$candidates[ $capability ] = true;
			}
		}

		ksort( $candidates, SORT_NATURAL );
		return $candidates;
	}
}
