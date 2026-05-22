<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_notices', 'application_password_scoper_unsupported_notice' );
add_action( 'network_admin_notices', 'application_password_scoper_unsupported_notice' );

function application_password_scoper_unsupported_notice() {
	global $application_password_scoper_plugin_file, $application_password_scoper_unsupported_reason;

	$title = 'Application Password Scoper cannot run';
	if ( 'wordpress' === $application_password_scoper_unsupported_reason ) {
		$message = 'Application Password Scoper requires WordPress 7.0 or newer.';
	}
	elseif ( 'autoload' === $application_password_scoper_unsupported_reason ) {
		$message = 'Application Password Scoper is missing Composer autoload files. Run composer dump-autoload before activating or testing the plugin.';
	}
	else {
		$message = 'Application Password Scoper requires PHP 8.2 or newer.';
	}

	$deactivate_url = add_query_arg(
		array(
			'action'   => 'deactivate',
			'plugin'   => urlencode( $application_password_scoper_plugin_file ),
			'_wpnonce' => wp_create_nonce( 'deactivate-plugin_'.$application_password_scoper_plugin_file )
		),
		self_admin_url( 'plugins.php' )
	);

	echo sprintf(
		'<div class="notice notice-error"><p><strong>%s</strong></p><p>%s</p><p><a href="%s">%s</a></p></div>',
		esc_html( $title ),
		esc_html( $message ),
		esc_url( $deactivate_url ),
		esc_html( 'Deactivate Application Password Scoper' )
	);
}
