<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Templates run inside AdminTemplateRenderer::render(), so these are local view variables.

$mdpsc_item = $mdpscTemplateData[ 'item' ];

?>
<div class="mdpsc-capability-item" data-mdpsc-capability-item data-mdpsc-capability-key="<?php echo esc_attr( $mdpsc_item[ 'item_key' ] ); ?>" data-mdpsc-capability-name="<?php echo esc_attr( $mdpsc_item[ 'name' ] ); ?>" data-mdpsc-capability-type="<?php echo esc_attr( $mdpsc_item[ 'type' ] ); ?>" data-mdpsc-capability-source="<?php echo esc_attr( $mdpsc_item[ 'source' ] ); ?>" data-mdpsc-capability-area="<?php echo esc_attr( $mdpsc_item[ 'area' ] ); ?>" data-mdpsc-capability-action="<?php echo esc_attr( $mdpsc_item[ 'action' ] ); ?>">
	<input id="<?php echo esc_attr( $mdpsc_item[ 'input_id' ] ); ?>" type="checkbox" name="<?php echo esc_attr( $mdpsc_item[ 'field_name' ] ); ?>[]" value="<?php echo esc_attr( $mdpsc_item[ 'name' ] ); ?>"<?php if ( $mdpsc_item[ 'checked' ] ) { ?> checked="checked"<?php } ?><?php if ( $mdpsc_item[ 'disabled' ] ) { ?> disabled="disabled"<?php } ?> />
	<label class="mdpsc-capability-name" for="<?php echo esc_attr( $mdpsc_item[ 'input_id' ] ); ?>">
		<code><?php echo esc_html( $mdpsc_item[ 'name' ] ); ?></code>
	</label>
	<span class="mdpsc-capability-action-badge" tabindex="0" aria-label="<?php echo esc_attr( $mdpsc_item[ 'action_label' ] ); ?>" data-mdpsc-tooltip data-mdpsc-tooltip-text="<?php echo esc_attr( $mdpsc_item[ 'action_label' ] ); ?>"><?php echo esc_html( $mdpsc_item[ 'action_abbreviation' ] ); ?></span>
	<?php
	if ( $mdpsc_item[ 'has_tooltip' ] ) {
		?>
		<button type="button" class="mdpsc-capability-info" aria-label="<?php echo esc_attr( $mdpsc_item[ 'tooltip_aria_label' ] ); ?>" data-mdpsc-tooltip data-mdpsc-tooltip-text="<?php echo esc_attr( $mdpsc_item[ 'tooltip_text' ] ); ?>"></button>
		<?php
	}
	else {
		?>
		<span class="mdpsc-capability-info-space" aria-hidden="true"></span>
		<?php
	}
	?>
</div>
