<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Admin;

class AdminTrustedHtmlSanitizer {

	public function dropdown( string $html ) :string {
		return \wp_kses(
			$html,
			[
				'select' => [
					'class' => true,
					'id'    => true,
					'name'  => true,
				],
				'option' => [
					'selected' => true,
					'value'    => true,
				],
			]
		);
	}

	public function nonceFields( string $html ) :string {
		$sanitized = \wp_kses(
			$html,
			[
				'input' => [
					'id'    => true,
					'name'  => true,
					'type'  => true,
					'value' => true,
				],
			]
		);

		return $this->hiddenInputsOnly( $sanitized );
	}

	private function hiddenInputsOnly( string $html ) :string {
		preg_match_all( '/<input\b[^>]*>/i', $html, $matches );

		$hiddenInputs = [];
		foreach ( $matches[ 0 ] as $input ) {
			if ( preg_match( '/\stype\s*=\s*(["\'])hidden\1/i', $input ) === 1 ) {
				$hiddenInputs[] = $input;
			}
		}

		return implode( '', $hiddenInputs );
	}
}
