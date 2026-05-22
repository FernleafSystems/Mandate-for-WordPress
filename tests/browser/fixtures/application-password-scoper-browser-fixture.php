<?php
/*
 * Test-only fixture endpoint for the local browser lane.
 */

add_action(
	'rest_api_init',
	static function () :void {
		register_rest_route(
			'application-password-scoper-test/v1',
			'/fixture',
			[
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => static fn() => rest_ensure_response( get_option( 'application_password_scoper_browser_fixture', [] ) ),
			]
		);

		register_rest_route(
			'application-password-scoper-test/v1',
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
							'aps_manage_widget' => current_user_can( 'aps_manage_widget' ),
						]
					);
				},
			]
		);
	}
);
