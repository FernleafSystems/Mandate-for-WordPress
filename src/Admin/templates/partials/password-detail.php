<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="mandate-password-summary-detail">
	<?php
	if ( $detail[ 'kind' ] === 'admin_lock' ) {
		?>
		<dt><label for="<?php echo esc_attr( $detail[ 'input' ][ 'id' ] ); ?>"><?php echo esc_html( $detail[ 'label' ] ); ?></label></dt>
		<dd>
			<span class="mandate-admin-lock-control">
				<input type="checkbox" id="<?php echo esc_attr( $detail[ 'input' ][ 'id' ] ); ?>" name="<?php echo esc_attr( $detail[ 'input' ][ 'name' ] ); ?>" value="<?php echo esc_attr( $detail[ 'input' ][ 'value' ] ); ?>" form="<?php echo esc_attr( $detail[ 'input' ][ 'form' ] ); ?>" data-wpm-admin-lock-input<?php if ( $detail[ 'input' ][ 'checked' ] ) { ?> checked="checked"<?php } ?><?php if ( $detail[ 'input' ][ 'disabled' ] ) { ?> disabled="disabled"<?php } ?> />
			</span>
			<p class="description mandate-admin-lock-help"><?php echo esc_html( $detail[ 'help_text' ] ); ?></p>
		</dd>
		<?php
	}
	else {
		?>
		<dt><?php echo esc_html( $detail[ 'label' ] ); ?></dt>
		<dd>
			<?php
			if ( $detail[ 'kind' ] === 'expiration' ) {
				?>
				<button type="button" class="<?php echo esc_attr( $detail[ 'classes' ] ); ?>" data-wpm-expiration-summary data-wpm-expiration-state="<?php echo esc_attr( $detail[ 'state' ] ); ?>" aria-controls="<?php echo esc_attr( $detail[ 'input' ][ 'id' ] ); ?>"<?php if ( $detail[ 'disabled' ] ) { ?> disabled="disabled"<?php } ?> hidden><?php echo esc_html( $detail[ 'value' ] ); ?></button>
				<input type="date" id="<?php echo esc_attr( $detail[ 'input' ][ 'id' ] ); ?>" class="mandate-expiration-input" name="<?php echo esc_attr( $detail[ 'input' ][ 'name' ] ); ?>" value="<?php echo esc_attr( $detail[ 'input' ][ 'value' ] ); ?>" data-wpm-expiration-input form="<?php echo esc_attr( $detail[ 'input' ][ 'form' ] ); ?>" aria-label="<?php echo esc_attr( $detail[ 'input' ][ 'aria_label' ] ); ?>"<?php if ( $detail[ 'input' ][ 'disabled' ] ) { ?> disabled="disabled"<?php } ?> />
				<?php
			}
			else {
				echo esc_html( $detail[ 'value' ] );
			}
			?>
		</dd>
		<?php
	}
	?>
</div>
