<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Admin;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class AdminUserRoleProvider {

	/**
	 * @return list<string>
	 */
	public function roleSlugsForUser( int $userId ) :array {
		$user = $userId > 0 ? get_userdata( $userId ) : false;
		if ( !is_object( $user ) || !isset( $user->roles ) || !is_array( $user->roles ) ) {
			return [];
		}

		$slugs = [];
		foreach ( $user->roles as $role ) {
			if ( !is_scalar( $role ) ) {
				continue;
			}

			$slug = sanitize_key( (string)$role );
			if ( $slug !== '' ) {
				$slugs[ $slug ] = $slug;
			}
		}

		ksort( $slugs, SORT_NATURAL );
		return array_values( $slugs );
	}

	/**
	 * @param list<string> $roleSlugs
	 * @return list<array{name:string,slug:string}>
	 */
	public function roleSummaries( array $roleSlugs ) :array {
		$wpRoles = function_exists( 'wp_roles' ) ? wp_roles() : null;
		$summaries = [];
		foreach ( $roleSlugs as $roleSlug ) {
			$registered = is_object( $wpRoles ) && method_exists( $wpRoles, 'get_role' )
				? $wpRoles->get_role( $roleSlug )
				: null;
			$summaries[] = [
				'name' => $registered === null ? $roleSlug : $this->roleDisplayName( $roleSlug, $wpRoles ),
				'slug' => $roleSlug,
			];
		}

		return $summaries;
	}

	private function roleDisplayName( string $roleSlug, mixed $wpRoles ) :string {
		$roleNames = [];
		if ( is_object( $wpRoles ) && method_exists( $wpRoles, 'get_names' ) ) {
			$roleNames = $wpRoles->get_names();
		}
		elseif ( is_object( $wpRoles ) && isset( $wpRoles->role_names ) && is_array( $wpRoles->role_names ) ) {
			$roleNames = $wpRoles->role_names;
		}

		$name = isset( $roleNames[ $roleSlug ] ) ? (string)$roleNames[ $roleSlug ] : $roleSlug;
		return function_exists( 'translate_user_role' ) ? translate_user_role( $name ) : $name;
	}
}
