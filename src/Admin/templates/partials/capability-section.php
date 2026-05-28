<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Templates run inside AdminTemplateRenderer::render(), so these are local view variables.

$mdpsc_section = $mdpscTemplateData[ 'section' ];

?>
<fieldset id="<?php echo esc_attr( $mdpsc_section[ 'id' ] ); ?>" class="mdpsc-capability-section" data-mdpsc-capability-section>
	<legend>
		<span class="mdpsc-capability-section-title">
			<span><?php echo esc_html( $mdpsc_section[ 'label' ] ); ?></span>
			<span class="mdpsc-capability-section-count"><?php echo esc_html( (string)$mdpsc_section[ 'count' ] ); ?></span>
		</span>
		<span class="mdpsc-capability-section-actions">
			<button type="button" class="mdpsc-link-button" data-mdpsc-select-section data-mdpsc-select-state="<?php echo esc_attr( $mdpsc_section[ 'bulk_actions' ][ 'select_all' ][ 'state' ] ); ?>"<?php if ( $mdpsc_section[ 'bulk_actions' ][ 'select_all' ][ 'disabled' ] ) { ?> disabled="disabled"<?php } ?>><?php echo esc_html( $mdpsc_section[ 'bulk_actions' ][ 'select_all' ][ 'label' ] ); ?></button>
			<span class="mdpsc-capability-section-action-separator" aria-hidden="true">/</span>
			<button type="button" class="mdpsc-link-button" data-mdpsc-select-section data-mdpsc-select-state="<?php echo esc_attr( $mdpsc_section[ 'bulk_actions' ][ 'deselect_all' ][ 'state' ] ); ?>"<?php if ( $mdpsc_section[ 'bulk_actions' ][ 'deselect_all' ][ 'disabled' ] ) { ?> disabled="disabled"<?php } ?>><?php echo esc_html( $mdpsc_section[ 'bulk_actions' ][ 'deselect_all' ][ 'label' ] ); ?></button>
		</span>
	</legend>
	<div class="mdpsc-capability-list">
		<?php
		foreach ( $mdpsc_section[ 'items' ] as $mdpsc_item ) {
			$mdpsc_item_html = $this->render( 'partials/capability-item.php', [ 'item' => $mdpsc_item ] );
			echo wp_kses( $mdpsc_item_html, $this->allowedAdminHtml() );
		}
		?>
	</div>
</fieldset>
