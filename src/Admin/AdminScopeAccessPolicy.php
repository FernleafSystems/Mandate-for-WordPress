<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Admin;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class AdminScopeAccessPolicy {

	private const PAGE_CAPABILITY = 'read';
	private const ADMIN_CAPABILITY = 'manage_options';

	public function pageCapability() :string {
		return self::PAGE_CAPABILITY;
	}

	public function canAccessPage() :bool {
		$currentUserId = $this->currentUserId();

		return $currentUserId > 0
			&& ( $this->canManageAnyScope() || $this->canSelfManageOwnScope( $currentUserId ) );
	}

	/**
	 * @param array<string,true> $candidateCaps
	 */
	public function canAdminLockScopeForCaps( array $candidateCaps ) :bool {
		return !isset( $candidateCaps[ self::ADMIN_CAPABILITY ] );
	}

	public function canManageAnyScope() :bool {
		return current_user_can( self::ADMIN_CAPABILITY );
	}

	public function canManageUserScope( int $userId ) :bool {
		if ( $userId < 1 ) {
			return false;
		}

		return $this->canManageAnyScope() || $this->canSelfManageOwnScope( $userId );
	}

	/**
	 * @param array{admin_locked:bool}|null $scope
	 */
	public function canMutateScope( int $userId, ?array $scope ) :bool {
		if ( !$this->canManageUserScope( $userId ) ) {
			return false;
		}

		return $this->canManageAnyScope() || $scope === null || !$scope[ 'admin_locked' ];
	}

	/**
	 * @param array{admin_locked:bool}|null $scope
	 */
	public function isReadOnlyScope( int $userId, ?array $scope ) :bool {
		return $userId > 0
			&& !$this->canManageAnyScope()
			&& $scope !== null
			&& $scope[ 'admin_locked' ];
	}

	public function selectedUserId( string $requestedUserId ) :int {
		$currentUserId = $this->currentUserId();
		if ( !$this->canManageAnyScope() ) {
			return $this->canSelfManageOwnScope( $currentUserId ) ? $currentUserId : 0;
		}

		$userId = absint( $requestedUserId );
		if ( $this->existingUserId( $userId ) > 0 ) {
			return $userId;
		}

		return $this->existingUserId( $currentUserId );
	}

	public function shouldRestrictUserSelection() :bool {
		return !$this->canManageAnyScope();
	}

	public function canUseScopeShortcutForProfileUser( int $profileUserId ) :bool {
		return $profileUserId > 0
			&& ( $this->canManageAnyScope() || $this->canSelfManageOwnScope( $profileUserId ) );
	}

	public function currentUserId() :int {
		return (int)get_current_user_id();
	}

	private function existingUserId( int $userId ) :int {
		return $userId > 0 && get_userdata( $userId ) ? $userId : 0;
	}

	private function canSelfManageOwnScope( int $userId ) :bool {
		return $userId > 0
			&& $userId === $this->currentUserId()
			&& current_user_can( self::PAGE_CAPABILITY )
			&& $this->existingUserId( $userId ) > 0
			&& $this->applicationPasswordsAvailableForUser( $userId );
	}

	private function applicationPasswordsAvailableForUser( int $userId ) :bool {
		return wp_is_application_passwords_available_for_user( $userId );
	}
}
