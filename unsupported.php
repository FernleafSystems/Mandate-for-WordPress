<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

return static function ( string $pluginFile, string $reason ) :void {
	$notice = static function () use ( $pluginFile, $reason ) :void {
		$title = 'Mandate App Security cannot run';
		if ( 'wordpress' === $reason ) {
			$message = 'Mandate App Security requires WordPress 7.0 or newer.';
		}
		elseif ( 'autoload' === $reason ) {
			$message = 'Mandate App Security is missing Composer autoload files. Run composer dump-autoload before activating or testing the plugin.';
		}
		else {
			$message = 'Mandate App Security requires PHP 8.2 or newer.';
		}

		$deactivateUrl = add_query_arg(
			array(
				'action'   => 'deactivate',
				'plugin'   => urlencode( $pluginFile ),
				'_wpnonce' => wp_create_nonce( 'deactivate-plugin_'.$pluginFile )
			),
			self_admin_url( 'plugins.php' )
		);

		echo sprintf(
			'<div class="notice notice-error"><p><strong>%s</strong></p><p>%s</p><p><a href="%s">%s</a></p></div>',
			esc_html( $title ),
			esc_html( $message ),
			esc_url( $deactivateUrl ),
			esc_html( 'Deactivate Mandate App Security' )
		);
	};

	add_action( 'admin_notices', $notice );
	add_action( 'network_admin_notices', $notice );
};
