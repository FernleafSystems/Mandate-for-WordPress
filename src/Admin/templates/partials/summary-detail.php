<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Templates run inside AdminTemplateRenderer::render(), so these are local view variables.

$mdpsc_detail = $mdpscTemplateData[ 'detail' ];

?>
<div class="mdpsc-summary-detail">
	<?php
	if ( $mdpsc_detail[ 'kind' ] === 'admin_lock' ) {
		?>
		<dt><label for="<?php echo esc_attr( $mdpsc_detail[ 'input' ][ 'id' ] ); ?>"><?php echo esc_html( $mdpsc_detail[ 'label' ] ); ?></label></dt>
		<dd>
			<span class="mdpsc-admin-lock-control">
				<input type="checkbox" id="<?php echo esc_attr( $mdpsc_detail[ 'input' ][ 'id' ] ); ?>" name="<?php echo esc_attr( $mdpsc_detail[ 'input' ][ 'name' ] ); ?>" value="<?php echo esc_attr( $mdpsc_detail[ 'input' ][ 'value' ] ); ?>" form="<?php echo esc_attr( $mdpsc_detail[ 'input' ][ 'form' ] ); ?>" data-mdpsc-admin-lock-input<?php if ( $mdpsc_detail[ 'input' ][ 'checked' ] ) { ?> checked="checked"<?php } ?><?php if ( $mdpsc_detail[ 'input' ][ 'disabled' ] ) { ?> disabled="disabled"<?php } ?> />
			</span>
			<p class="description mdpsc-admin-lock-help"><?php echo esc_html( $mdpsc_detail[ 'help_text' ] ); ?></p>
		</dd>
		<?php
	}
	else {
		?>
		<dt><?php echo esc_html( $mdpsc_detail[ 'label' ] ); ?></dt>
		<dd>
			<?php
			if ( $mdpsc_detail[ 'kind' ] === 'expiration' ) {
				?>
				<button type="button" class="<?php echo esc_attr( $mdpsc_detail[ 'classes' ] ); ?>" data-mdpsc-expiration-summary data-mdpsc-expiration-state="<?php echo esc_attr( $mdpsc_detail[ 'state' ] ); ?>" aria-controls="<?php echo esc_attr( $mdpsc_detail[ 'input' ][ 'id' ] ); ?>"<?php if ( $mdpsc_detail[ 'disabled' ] ) { ?> disabled="disabled"<?php } ?> hidden><?php echo esc_html( $mdpsc_detail[ 'value' ] ); ?></button>
				<input type="date" id="<?php echo esc_attr( $mdpsc_detail[ 'input' ][ 'id' ] ); ?>" class="mdpsc-expiration-input" name="<?php echo esc_attr( $mdpsc_detail[ 'input' ][ 'name' ] ); ?>" value="<?php echo esc_attr( $mdpsc_detail[ 'input' ][ 'value' ] ); ?>" data-mdpsc-expiration-input form="<?php echo esc_attr( $mdpsc_detail[ 'input' ][ 'form' ] ); ?>" aria-label="<?php echo esc_attr( $mdpsc_detail[ 'input' ][ 'aria_label' ] ); ?>"<?php if ( $mdpsc_detail[ 'input' ][ 'disabled' ] ) { ?> disabled="disabled"<?php } ?> />
				<?php
			}
			else {
				echo esc_html( $mdpsc_detail[ 'value' ] );
			}
			?>
		</dd>
		<?php
	}
	?>
</div>
