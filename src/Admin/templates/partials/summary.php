<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

if ( !$summary[ 'is_visible' ] ) {
	return;
}
?>
<div id="<?php echo esc_attr( $summary[ 'container_id' ] ); ?>" class="mandate-detail-summary mandate-summary-card" aria-labelledby="<?php echo esc_attr( $summary[ 'title_id' ] ); ?>">
	<p id="<?php echo esc_attr( $summary[ 'title_id' ] ); ?>" class="mandate-summary-title"><?php echo esc_html( $summary[ 'title' ] ); ?></p>
	<dl class="mandate-summary-details">
		<?php
		foreach ( $summary[ 'details' ] as $mandateDetail ) {
			$mandateDetailHtml = $this->render( 'partials/summary-detail.php', [ 'detail' => $mandateDetail ] );
			echo wp_kses( $mandateDetailHtml, $this->allowedAdminHtml() );
		}
		?>
	</dl>
	<?php
	foreach ( $summary[ 'warnings' ] as $mandateWarning ) {
		?>
		<div class="<?php echo esc_attr( $mandateWarning[ 'classes' ] ); ?>" data-wpm-role-snapshot-status="<?php echo esc_attr( $mandateWarning[ 'role_snapshot_status' ] ); ?>"><p><?php echo esc_html( $mandateWarning[ 'text' ] ); ?></p></div>
		<?php
	}
	?>
</div>
