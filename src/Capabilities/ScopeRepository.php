<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Capabilities;

use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\ApplicationPasswords\ApplicationPasswordRepository;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Expiration\ExpirationDatePolicy;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Options\PluginOptionsRepository;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @phpstan-type CapabilityScopeRecord array{user_id:int,capabilities_restricted:bool,allowed_caps:array<string,true>,allowed_meta_caps:array<string,true>,expires_on:string|null,roles_at_update:list<string>|null,updated_at:int,updated_by:int,admin_locked:bool}
 */
class ScopeRepository {

	private PluginOptionsRepository $optionsRepository;

	private ExpirationDatePolicy $expirationDatePolicy;

	public function __construct( PluginOptionsRepository $optionsRepository, ExpirationDatePolicy $expirationDatePolicy ) {
		$this->optionsRepository = $optionsRepository;
		$this->expirationDatePolicy = $expirationDatePolicy;
	}

	/**
	 * @return array<string,CapabilityScopeRecord>
	 */
	public function all() :array {
		$normalized = [];
		foreach ( $this->optionsRepository->scopes() as $uuid => $record ) {
			$uuid = ApplicationPasswordRepository::normalizeUuid( (string)$uuid );
			$record = is_array( $record ) ? $this->normalizeRecord( $record ) : null;
			if ( $uuid !== '' && $record !== null ) {
				$normalized[ $uuid ] = $record;
			}
		}

		return $normalized;
	}

	/**
	 * @return CapabilityScopeRecord|null
	 */
	public function find( string $uuid ) :?array {
		$uuid = ApplicationPasswordRepository::normalizeUuid( $uuid );
		if ( $uuid === '' ) {
			return null;
		}

		$all = $this->all();
		return $all[ $uuid ] ?? null;
	}

	/**
	 * @return CapabilityScopeRecord|null
	 */
	public function findForUser( int $userId, string $uuid ) :?array {
		if ( $userId < 1 ) {
			return null;
		}

		$record = $this->find( $uuid );
		if ( $record === null || $record[ 'user_id' ] !== $userId ) {
			return null;
		}

		return $record;
	}

	/**
	 * @param array<string,true> $allowedCaps
	 * @param array<string,true> $allowedMetaCaps
	 * @param string[] $rolesAtUpdate
	 */
	public function save(
		string $uuid,
		int $userId,
		array $allowedCaps,
		array $allowedMetaCaps,
		array $rolesAtUpdate,
		int $updatedBy,
		?string $expiresOn = null,
		bool $capabilitiesRestricted = true,
		bool $adminLocked = false
	) :bool {
		$uuid = ApplicationPasswordRepository::normalizeUuid( $uuid );
		if ( $uuid === '' || $userId < 1 ) {
			return false;
		}
		$submittedExpiresOn = $expiresOn;
		$expiresOn = $submittedExpiresOn === null ? null : $this->expirationDatePolicy->normalize( $submittedExpiresOn );
		if ( $submittedExpiresOn !== null && $expiresOn === null ) {
			return false;
		}

		$all = $this->all();
		$all[ $uuid ] = [
			'user_id'                 => $userId,
			'capabilities_restricted' => $capabilitiesRestricted,
			'allowed_caps'            => $capabilitiesRestricted ? CapabilityName::normalizeMap( $allowedCaps ) : [],
			'allowed_meta_caps'       => $capabilitiesRestricted ? CapabilityName::normalizeMap( $allowedMetaCaps ) : [],
			'expires_on'              => $expiresOn,
			'roles_at_update'         => $this->normalizeRoleSlugs( $rolesAtUpdate ),
			'updated_at'              => time(),
			'updated_by'              => max( 0, $updatedBy ),
			'admin_locked'            => $adminLocked,
		];

		return $this->optionsRepository->replaceScopes( $all );
	}

	public function deleteForUser( int $userId, string $uuid ) :bool {
		if ( $userId < 1 ) {
			return false;
		}

		$uuid = ApplicationPasswordRepository::normalizeUuid( $uuid );
		if ( $uuid === '' ) {
			return false;
		}

		$all = $this->all();
		if ( !isset( $all[ $uuid ] ) ) {
			return true;
		}
		if ( $all[ $uuid ][ 'user_id' ] !== $userId ) {
			return false;
		}

		unset( $all[ $uuid ] );
		return $this->optionsRepository->replaceScopes( $all );
	}

	/**
	 * @param array<string,mixed> $item
	 */
	public function deleteForApplicationPassword( int $userId, array $item ) :void {
		$uuid = isset( $item[ 'uuid' ] ) ? (string)$item[ 'uuid' ] : '';
		$this->deleteForUser( $userId, $uuid );
	}

	/**
	 * @param array<string,mixed> $record
	 * @return CapabilityScopeRecord|null
	 */
	public function normalizeRecord( array $record ) :?array {
		$userId = isset( $record[ 'user_id' ] ) ? (int)$record[ 'user_id' ] : 0;
		if ( $userId < 1 ) {
			return null;
		}

		$allowedCaps = isset( $record[ 'allowed_caps' ] ) && is_array( $record[ 'allowed_caps' ] )
			? CapabilityName::normalizeMap( $record[ 'allowed_caps' ] )
			: [];
		$allowedMetaCaps = isset( $record[ 'allowed_meta_caps' ] ) && is_array( $record[ 'allowed_meta_caps' ] )
			? CapabilityName::normalizeMap( $record[ 'allowed_meta_caps' ] )
			: [];
		$rolesAtUpdate = isset( $record[ 'roles_at_update' ] ) && is_array( $record[ 'roles_at_update' ] )
			? $this->normalizeRoleSlugs( $record[ 'roles_at_update' ] )
			: null;
		$capabilitiesRestricted = isset( $record[ 'capabilities_restricted' ] ) && is_bool( $record[ 'capabilities_restricted' ] )
			? $record[ 'capabilities_restricted' ]
			: true;
		$expiresOn = array_key_exists( 'expires_on', $record )
			? $this->expirationDatePolicy->normalize( $record[ 'expires_on' ] )
			: null;
		$adminLocked = isset( $record[ 'admin_locked' ] ) && is_bool( $record[ 'admin_locked' ] )
			? $record[ 'admin_locked' ]
			: false;

		return [
			'user_id'                 => $userId,
			'capabilities_restricted' => $capabilitiesRestricted,
			'allowed_caps'            => $capabilitiesRestricted ? $allowedCaps : [],
			'allowed_meta_caps'       => $capabilitiesRestricted ? $allowedMetaCaps : [],
			'expires_on'              => $expiresOn,
			'roles_at_update'         => $rolesAtUpdate,
			'updated_at'              => isset( $record[ 'updated_at' ] ) ? max( 0, (int)$record[ 'updated_at' ] ) : 0,
			'updated_by'              => isset( $record[ 'updated_by' ] ) ? max( 0, (int)$record[ 'updated_by' ] ) : 0,
			'admin_locked'            => $adminLocked,
		];
	}

	/**
	 * @param array<int|string,mixed> $roles
	 * @return list<string>
	 */
	private function normalizeRoleSlugs( array $roles ) :array {
		$normalized = [];
		foreach ( $roles as $role ) {
			if ( !is_scalar( $role ) ) {
				continue;
			}

			$slug = trim( (string)$role );
			if ( $slug === '' ) {
				continue;
			}

			$slug = sanitize_key( $slug );
			if ( $slug !== '' ) {
				$normalized[ $slug ] = $slug;
			}
		}

		ksort( $normalized, SORT_NATURAL );
		return array_values( $normalized );
	}
}
