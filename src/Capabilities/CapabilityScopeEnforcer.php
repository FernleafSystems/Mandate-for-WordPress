<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Capabilities;

use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\ApplicationPasswords\CurrentApplicationPasswordContext;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Expiration\ExpirationDatePolicy;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\MetaCaps\MetaCapabilityRegistry;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @phpstan-import-type CapabilityScopeRecord from ScopeRepository
 */
class CapabilityScopeEnforcer {

	private ScopeRepository $scopeRepository;

	private CapabilityCandidateProvider $candidateProvider;

	private CurrentApplicationPasswordContext $context;

	private MetaCapabilityRegistry $metaRegistry;

	private ExpirationDatePolicy $expirationDatePolicy;

	public function __construct(
		ScopeRepository $scopeRepository,
		CapabilityCandidateProvider $candidateProvider,
		CurrentApplicationPasswordContext $context,
		MetaCapabilityRegistry $metaRegistry,
		ExpirationDatePolicy $expirationDatePolicy
	) {
		$this->scopeRepository = $scopeRepository;
		$this->candidateProvider = $candidateProvider;
		$this->context = $context;
		$this->metaRegistry = $metaRegistry;
		$this->expirationDatePolicy = $expirationDatePolicy;
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
		if ( $this->expirationDatePolicy->isExpired( $scope[ 'expires_on' ] ) ) {
			return $this->removeGrantedCapabilities( $allcaps );
		}
		if ( !$scope[ 'capabilities_restricted' ] ) {
			return $allcaps;
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
		if ( $this->expirationDatePolicy->isExpired( $scope[ 'expires_on' ] ) ) {
			return [ 'do_not_allow' ];
		}
		if ( !$scope[ 'capabilities_restricted' ] ) {
			return $caps;
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
	 * @return CapabilityScopeRecord|null
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
		return is_multisite() && is_super_admin( $userId );
	}
}
