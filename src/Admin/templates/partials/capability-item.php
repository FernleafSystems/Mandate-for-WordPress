<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

?>
<label>
	<input type="checkbox" name="<?php echo esc_attr( $item[ 'field_name' ] ); ?>[]" value="<?php echo esc_attr( $item[ 'name' ] ); ?>"<?php if ( $item[ 'checked' ] ) { ?> checked="checked"<?php } ?><?php if ( $item[ 'disabled' ] ) { ?> disabled="disabled"<?php } ?> />
	<?php
	if ( $item[ 'has_tooltip' ] ) {
		?>
		<code tabindex="0" data-wpm-tooltip data-wpm-tooltip-text="<?php echo esc_attr( $item[ 'tooltip_text' ] ); ?>"><?php echo esc_html( $item[ 'name' ] ); ?></code>
		<?php
	}
	else {
		?>
		<code><?php echo esc_html( $item[ 'name' ] ); ?></code>
		<?php
	}
	?>
</label>
