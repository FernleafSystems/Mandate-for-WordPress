<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Admin;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

final class AdminProfileContext {

	public function __construct(
		private AdminRequest $request,
		private AdminScopeAccessPolicy $accessPolicy
	) {
	}

	public function profileUserId() :int {
		$requestedUserId = absint( $this->request->getScalar( 'user_id' ) );
		if ( $this->existingUserId( $requestedUserId ) > 0 ) {
			return $requestedUserId;
		}

		if ( !$this->request->isOwnProfileScreen() ) {
			return 0;
		}

		return $this->existingUserId( $this->accessPolicy->currentUserId() );
	}

	private function existingUserId( int $userId ) :int {
		return $userId > 0 && get_userdata( $userId ) ? $userId : 0;
	}
}
