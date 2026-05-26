<?php
/*
 * Test-only fixture endpoint for the local browser lane.
 */

function wpm_browser_fixture_user( string $login, string $email, string $role ) :WP_User {
	$user = get_user_by( 'login', $login );
	if ( $user instanceof WP_User ) {
		$user->set_role( $role );
		return $user;
	}

	$userId = wp_insert_user(
		[
			'user_login' => $login,
			'user_pass'  => 'password',
			'user_email' => $email,
			'role'       => $role,
		]
	);
	if ( is_wp_error( $userId ) ) {
		throw new RuntimeException( $userId->get_error_message() );
	}

	$user = get_user_by( 'id', (int)$userId );
	if ( !$user instanceof WP_User ) {
		throw new RuntimeException( 'Fixture user could not be loaded.' );
	}

	return $user;
}

function wpm_browser_fixture_password( int $userId, string $name ) :array {
	[ $plainPassword, $item ] = WP_Application_Passwords::create_new_application_password(
		$userId,
		[
			'name'   => $name,
			'app_id' => wp_generate_uuid4(),
		]
	);

	return [
		'uuid'         => $item[ 'uuid' ],
		'app_password' => $plainPassword,
		'name'         => $name,
	];
}

function wpm_browser_fixture_reset() :array {
	$limitedRole = 'wpm_limited';
	$otherRole = 'wpm_other';

	remove_role( $limitedRole );
	remove_role( $otherRole );
	add_role(
		$limitedRole,
		'APS Limited',
		[
			'read'              => true,
			'edit_posts'        => true,
			'upload_files'      => true,
			'wpm_manage_widget' => true,
		]
	);
	add_role(
		$otherRole,
		'APS Other',
		[
			'read'           => true,
			'delete_posts'   => true,
			'manage_options' => true,
		]
	);

	$primaryUser = wpm_browser_fixture_user( 'wpm_user', 'wpm-user@example.com', $limitedRole );
	$primaryUser->add_cap( 'delete_posts', true );
	$otherUser = wpm_browser_fixture_user( 'wpm_other_user', 'wpm-other-user@example.com', $otherRole );

	WP_Application_Passwords::delete_all_application_passwords( (int)$primaryUser->ID );
	WP_Application_Passwords::delete_all_application_passwords( (int)$otherUser->ID );

	$primaryPassword = wpm_browser_fixture_password( (int)$primaryUser->ID, 'WPM Browser Primary' );
	$secondaryPassword = wpm_browser_fixture_password( (int)$primaryUser->ID, 'WPM Browser Secondary' );
	$otherPassword = wpm_browser_fixture_password( (int)$otherUser->ID, 'WPM Browser Other' );

	( new \FernleafSystems\Wordpress\Plugin\Mandate\Options\PluginOptionsRepository() )->replaceScopes( [] );
	$fixture = [
		'primary'          => [
			'user_id'    => (int)$primaryUser->ID,
			'user_login' => 'wpm_user',
			'role_slug'  => $limitedRole,
			'role_name'  => 'APS Limited',
			'passwords'  => [
				'primary'   => $primaryPassword,
				'secondary' => $secondaryPassword,
			],
			'role_caps'  => [ 'read', 'edit_posts', 'upload_files', 'wpm_manage_widget' ],
			'direct_cap' => 'delete_posts',
		],
		'expiration_dates' => [
			'expired' => '2000-01-01',
			'future'  => wp_date( 'Y-m-d', strtotime( '+1 day' ) ),
		],
		'secondary_user'   => [
			'user_id'    => (int)$otherUser->ID,
			'user_login' => 'wpm_other_user',
			'role_slug'  => $otherRole,
			'role_name'  => 'APS Other',
			'passwords'  => [
				'primary' => $otherPassword,
			],
		],
		'unassigned_role_cap' => 'manage_options',
	];
	update_option( 'mandate_browser_fixture', $fixture, false );

	return $fixture;
}

add_action(
	'rest_api_init',
	static function () :void {
		register_rest_route(
			'mandate-test/v1',
			'/fixture',
			[
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => static fn() => rest_ensure_response( wpm_browser_fixture_reset() ),
			]
		);

		register_rest_route(
			'mandate-test/v1',
			'/caps',
			[
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => static function () {
					return rest_ensure_response(
						[
							'user_id'        => get_current_user_id(),
							'read'           => current_user_can( 'read' ),
							'edit_posts'     => current_user_can( 'edit_posts' ),
							'upload_files'   => current_user_can( 'upload_files' ),
							'delete_posts'   => current_user_can( 'delete_posts' ),
							'manage_options' => current_user_can( 'manage_options' ),
							'wpm_manage_widget' => current_user_can( 'wpm_manage_widget' ),
						]
					);
				},
			]
		);

		register_rest_route(
			'mandate-test/v1',
			'/expiration',
			[
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => static function ( WP_REST_Request $request ) {
					$userId = absint( $request->get_param( 'user_id' ) );
					$uuid = is_scalar( $request->get_param( 'uuid' ) ) ? (string)$request->get_param( 'uuid' ) : '';
					$expiresOn = is_scalar( $request->get_param( 'expires_on' ) ) ? (string)$request->get_param( 'expires_on' ) : '';
					$repository = new \FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\ScopeRepository(
						new \FernleafSystems\Wordpress\Plugin\Mandate\Options\PluginOptionsRepository(),
						new \FernleafSystems\Wordpress\Plugin\Mandate\Expiration\ExpirationDatePolicy()
					);

					return rest_ensure_response(
						[
							'saved' => $repository->save( $uuid, $userId, [], [], [], get_current_user_id(), $expiresOn, false ),
						]
					);
				},
			]
		);

		register_rest_route(
			'mandate-test/v1',
			'/run-expiration-cron',
			[
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => static function ( WP_REST_Request $request ) {
					do_action( \FernleafSystems\Wordpress\Plugin\Mandate\Expiration\ApplicationPasswordExpirationReaper::HOOK );
					$userId = absint( $request->get_param( 'user_id' ) );
					$uuid = is_scalar( $request->get_param( 'uuid' ) ) ? (string)$request->get_param( 'uuid' ) : '';
					$passwordExists = null;
					if ( $userId > 0 && $uuid !== '' ) {
						$passwordExists = false;
						foreach ( WP_Application_Passwords::get_user_application_passwords( $userId ) as $password ) {
							if ( is_array( $password ) && ( $password[ 'uuid' ] ?? null ) === $uuid ) {
								$passwordExists = true;
								break;
							}
						}
					}

					return rest_ensure_response(
						[
							'ran'             => true,
							'password_exists' => $passwordExists,
						]
					);
				},
			]
		);

		register_rest_route(
			'mandate-test/v1',
			'/auth',
			[
				'methods'             => 'GET',
				'permission_callback' => 'is_user_logged_in',
				'callback'            => static fn() => rest_ensure_response( [ 'user_id' => get_current_user_id() ] ),
			]
		);
	}
);
