<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Templates run inside AdminTemplateRenderer::render(), so these are local view variables.

$mdpsc_notice = $mdpscTemplateData[ 'notice' ];

if ( !$mdpsc_notice[ 'is_visible' ] ) {
	return;
}
?>
<div class="<?php echo esc_attr( $mdpsc_notice[ 'classes' ] ); ?>"><p><?php echo esc_html( $mdpsc_notice[ 'text' ] ); ?></p></div>
