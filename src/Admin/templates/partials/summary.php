<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

if ( !$summary[ 'is_visible' ] ) {
	return;
}
?>
<?php if ( $summary[ 'title_placement' ] === 'outside' ) { ?>
	<p id="<?php echo esc_attr( $summary[ 'title_id' ] ); ?>" class="mandate-field-title"><?php echo esc_html( $summary[ 'title' ] ); ?></p>
<?php } ?>
<div id="<?php echo esc_attr( $summary[ 'container_id' ] ); ?>" class="mandate-detail-summary mandate-summary-card" aria-labelledby="<?php echo esc_attr( $summary[ 'title_id' ] ); ?>">
	<?php if ( $summary[ 'title_placement' ] === 'inside' ) { ?>
		<p id="<?php echo esc_attr( $summary[ 'title_id' ] ); ?>" class="mandate-summary-title"><?php echo esc_html( $summary[ 'title' ] ); ?></p>
	<?php } ?>
	<dl class="mandate-summary-details">
		<?php
		foreach ( $summary[ 'details' ] as $mandate_app_security_detail ) {
			$mandate_app_security_detail_html = $this->render( 'partials/summary-detail.php', [ 'detail' => $mandate_app_security_detail ] );
			echo wp_kses( $mandate_app_security_detail_html, $this->allowedAdminHtml() );
		}
		?>
	</dl>
	<?php
	foreach ( $summary[ 'warnings' ] as $mandate_app_security_warning ) {
		?>
		<div class="<?php echo esc_attr( $mandate_app_security_warning[ 'classes' ] ); ?>" data-wpm-role-snapshot-status="<?php echo esc_attr( $mandate_app_security_warning[ 'role_snapshot_status' ] ); ?>"><p><?php echo esc_html( $mandate_app_security_warning[ 'text' ] ); ?></p></div>
		<?php
	}
	?>
</div>
