<?php

declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Capabilities;

use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\ApplicationPasswords\CurrentApplicationPasswordContext;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\MetaCaps\MetaCapabilityRegistry;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class CapabilityScopeEnforcer {

	private ScopeRepository $scopeRepository;

	private CapabilityCandidateProvider $candidateProvider;

	private CurrentApplicationPasswordContext $context;

	private MetaCapabilityRegistry $metaRegistry;

	public function __construct(
		ScopeRepository $scopeRepository,
		CapabilityCandidateProvider $candidateProvider,
		CurrentApplicationPasswordContext $context,
		MetaCapabilityRegistry $metaRegistry
	) {
		$this->scopeRepository = $scopeRepository;
		$this->candidateProvider = $candidateProvider;
		$this->context = $context;
		$this->metaRegistry = $metaRegistry;
	}

	public function registerHooks() :void {
		add_filter( 'user_has_cap', [ $this, 'filterUserCapabilities' ], PHP_INT_MAX, 4 );
		add_filter( 'map_meta_cap', [ $this, 'filterMetaCaps' ], PHP_INT_MAX, 4 );
	}

	/**
	 * @param array<string,bool> $allcaps
	 * @param string[] $caps
	 * @param array<int,mixed> $args
	 * @param mixed $user
	 * @return array<string,bool>
	 */
	public function filterUserCapabilities( array $allcaps, array $caps, array $args, mixed $user ) :array {
		$userId = $this->extractUserId( $user, $args );
		$scope = $this->scopeForCurrentRequest();
		if ( $scope === null ) {
			return $allcaps;
		}

		if ( $userId < 1 || $scope[ 'user_id' ] !== $userId || $this->contextUserMismatch( $scope[ 'user_id' ] ) ) {
			return $this->removeGrantedCapabilities( $allcaps );
		}

		$currentRoleCaps = $this->candidateProvider->forUser( $userId );
		$allowedCaps = array_intersect_key( $scope[ 'allowed_caps' ], $currentRoleCaps );
		foreach ( $allcaps as $capability => $granted ) {
			if ( $granted && !isset( $allowedCaps[ $capability ] ) ) {
				$allcaps[ $capability ] = false;
			}
		}

		return $allcaps;
	}

	/**
	 * @param string[] $caps
	 * @param array<int,mixed> $args
	 * @return string[]
	 */
	public function filterMetaCaps( array $caps, string $cap, int $userId, array $args ) :array {
		$scope = $this->scopeForCurrentRequest();
		if ( $scope === null ) {
			return $caps;
		}

		if ( $userId < 1 || $scope[ 'user_id' ] !== $userId || $this->contextUserMismatch( $scope[ 'user_id' ] ) ) {
			return [ 'do_not_allow' ];
		}

		$normalizedCap = CapabilityName::normalize( $cap );
		if ( $this->metaRegistry->isRegistered( $normalizedCap )
			&& !isset( $scope[ 'allowed_meta_caps' ][ $normalizedCap ] )
		) {
			return [ 'do_not_allow' ];
		}

		if ( $this->isScopedSuperAdmin( $userId ) ) {
			$currentRoleCaps = $this->candidateProvider->forUser( $userId );
			$allowedCaps = array_intersect_key( $scope[ 'allowed_caps' ], $currentRoleCaps );
			foreach ( $caps as $mappedCap ) {
				$mappedCap = CapabilityName::normalize( $mappedCap );
				if ( $mappedCap !== '' && $mappedCap !== 'do_not_allow' && !isset( $allowedCaps[ $mappedCap ] ) ) {
					return [ 'do_not_allow' ];
				}
			}
		}

		return $caps;
	}

	/**
	 * @return array{user_id:int,allowed_caps:array<string,true>,allowed_meta_caps:array<string,true>,updated_at:int,updated_by:int}|null
	 */
	private function scopeForCurrentRequest() :?array {
		$uuid = $this->context->uuid();
		return $uuid === null ? null : $this->scopeRepository->find( $uuid );
	}

	private function contextUserMismatch( int $scopeUserId ) :bool {
		$contextUserId = $this->context->userId();
		return $contextUserId !== null && $contextUserId !== $scopeUserId;
	}

	/**
	 * @param array<string,bool> $allcaps
	 * @return array<string,bool>
	 */
	private function removeGrantedCapabilities( array $allcaps ) :array {
		foreach ( $allcaps as $capability => $granted ) {
			if ( $granted ) {
				$allcaps[ $capability ] = false;
			}
		}

		return $allcaps;
	}

	/**
	 * @param mixed $user
	 * @param array<int,mixed> $args
	 */
	private function extractUserId( mixed $user, array $args ) :int {
		if ( is_object( $user ) && isset( $user->ID ) ) {
			return (int)$user->ID;
		}

		return isset( $args[ 1 ] ) ? (int)$args[ 1 ] : 0;
	}

	private function isScopedSuperAdmin( int $userId ) :bool {
		return function_exists( 'is_multisite' )
			&& is_multisite()
			&& function_exists( 'is_super_admin' )
			&& is_super_admin( $userId );
	}
}
