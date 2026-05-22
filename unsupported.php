<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_notices', 'mandate_unsupported_notice' );
add_action( 'network_admin_notices', 'mandate_unsupported_notice' );

function mandate_unsupported_notice() {
	global $mandate_plugin_file, $mandate_unsupported_reason;

	$title = 'Mandate cannot run';
	if ( 'wordpress' === $mandate_unsupported_reason ) {
		$message = 'Mandate requires WordPress 7.0 or newer.';
	}
	elseif ( 'autoload' === $mandate_unsupported_reason ) {
		$message = 'Mandate is missing Composer autoload files. Run composer dump-autoload before activating or testing the plugin.';
	}
	else {
		$message = 'Mandate requires PHP 8.2 or newer.';
	}

	$deactivate_url = add_query_arg(
		array(
			'action'   => 'deactivate',
			'plugin'   => urlencode( $mandate_plugin_file ),
			'_wpnonce' => wp_create_nonce( 'deactivate-plugin_'.$mandate_plugin_file )
		),
		self_admin_url( 'plugins.php' )
	);

	echo sprintf(
		'<div class="notice notice-error"><p><strong>%s</strong></p><p>%s</p><p><a href="%s">%s</a></p></div>',
		esc_html( $title ),
		esc_html( $message ),
		esc_url( $deactivate_url ),
		esc_html( 'Deactivate Mandate' )
	);
}
