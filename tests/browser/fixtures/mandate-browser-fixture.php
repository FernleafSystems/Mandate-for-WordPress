<?php
/*
 * Test-only fixture endpoint for the local browser lane.
 */

add_action(
	'rest_api_init',
	static function () :void {
		register_rest_route(
			'mandate-test/v1',
			'/fixture',
			[
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => static fn() => rest_ensure_response( get_option( 'mandate_browser_fixture', [] ) ),
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
