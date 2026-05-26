<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="mandate-password-summary-detail">
	<dt><?php echo esc_html( $detail[ 'label' ] ); ?></dt>
	<dd>
		<?php
		if ( $detail[ 'kind' ] === 'expiration' ) {
			?>
			<button type="button" class="<?php echo esc_attr( $detail[ 'classes' ] ); ?>" data-wpm-expiration-summary data-wpm-expiration-state="<?php echo esc_attr( $detail[ 'state' ] ); ?>" aria-controls="<?php echo esc_attr( $detail[ 'input' ][ 'id' ] ); ?>" hidden><?php echo esc_html( $detail[ 'value' ] ); ?></button>
			<input type="date" id="<?php echo esc_attr( $detail[ 'input' ][ 'id' ] ); ?>" class="mandate-expiration-input" name="<?php echo esc_attr( $detail[ 'input' ][ 'name' ] ); ?>" value="<?php echo esc_attr( $detail[ 'input' ][ 'value' ] ); ?>" data-wpm-expiration-input form="<?php echo esc_attr( $detail[ 'input' ][ 'form' ] ); ?>" aria-label="<?php echo esc_attr( $detail[ 'input' ][ 'aria_label' ] ); ?>" />
			<?php
		}
		else {
			echo esc_html( $detail[ 'value' ] );
		}
		?>
	</dd>
</div>
