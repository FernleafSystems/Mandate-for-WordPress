<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Admin;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class AdminTrustedHtmlSanitizer {

	public function dropdown( string $html ) :string {
		return \wp_kses(
			$html,
			[
				'select' => [
					'class'    => true,
					'disabled' => true,
					'id'       => true,
					'name'     => true,
				],
				'option' => [
					'disabled' => true,
					'selected' => true,
					'value'    => true,
				],
			]
		);
	}
}
