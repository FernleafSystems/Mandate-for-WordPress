<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="mandate-capability-item" data-wpm-capability-item data-wpm-capability-key="<?php echo esc_attr( $item[ 'item_key' ] ); ?>" data-wpm-capability-name="<?php echo esc_attr( $item[ 'name' ] ); ?>" data-wpm-capability-type="<?php echo esc_attr( $item[ 'type' ] ); ?>" data-wpm-capability-source="<?php echo esc_attr( $item[ 'source' ] ); ?>" data-wpm-capability-area="<?php echo esc_attr( $item[ 'area' ] ); ?>" data-wpm-capability-action="<?php echo esc_attr( $item[ 'action' ] ); ?>">
	<input id="<?php echo esc_attr( $item[ 'input_id' ] ); ?>" type="checkbox" name="<?php echo esc_attr( $item[ 'field_name' ] ); ?>[]" value="<?php echo esc_attr( $item[ 'name' ] ); ?>"<?php if ( $item[ 'checked' ] ) { ?> checked="checked"<?php } ?><?php if ( $item[ 'disabled' ] ) { ?> disabled="disabled"<?php } ?> />
	<label class="mandate-capability-name" for="<?php echo esc_attr( $item[ 'input_id' ] ); ?>">
		<code><?php echo esc_html( $item[ 'name' ] ); ?></code>
	</label>
	<span class="mandate-capability-action-badge" tabindex="0" aria-label="<?php echo esc_attr( $item[ 'action_label' ] ); ?>" data-wpm-tooltip data-wpm-tooltip-text="<?php echo esc_attr( $item[ 'action_label' ] ); ?>"><?php echo esc_html( $item[ 'action_abbreviation' ] ); ?></span>
	<?php
	if ( $item[ 'has_tooltip' ] ) {
		?>
		<button type="button" class="mandate-capability-info" aria-label="<?php echo esc_attr( $item[ 'tooltip_aria_label' ] ); ?>" data-wpm-tooltip data-wpm-tooltip-text="<?php echo esc_attr( $item[ 'tooltip_text' ] ); ?>"></button>
		<?php
	}
	else {
		?>
		<span class="mandate-capability-info-space" aria-hidden="true"></span>
		<?php
	}
	?>
</div>
