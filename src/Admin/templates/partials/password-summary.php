<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

if ( !$summary[ 'is_visible' ] ) {
	return;
}
?>
<h2 id="<?php echo esc_attr( $summary[ 'title_id' ] ); ?>" class="mandate-field-title"><?php echo esc_html( $summary[ 'title' ] ); ?></h2>
<div id="<?php echo esc_attr( $summary[ 'container_id' ] ); ?>" class="mandate-password-summary mandate-summary-card" aria-labelledby="<?php echo esc_attr( $summary[ 'title_id' ] ); ?>">
	<?php
	foreach ( $summary[ 'sections' ] as $mandateSection ) {
		if ( $mandateSection[ 'show_divider_before' ] ) {
			?>
			<div class="mandate-password-summary-divider" aria-hidden="true"></div>
			<?php
		}
		?>
		<dl class="mandate-password-summary-details">
			<?php
			foreach ( $mandateSection[ 'details' ] as $mandateDetail ) {
				$mandateDetailHtml = $this->render( 'partials/password-detail.php', [ 'detail' => $mandateDetail ] );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered partial templates escape their own scalar output.
				echo $mandateDetailHtml;
			}
			?>
		</dl>
		<?php
	}
	foreach ( $summary[ 'warnings' ] as $mandateWarning ) {
		?>
		<div class="<?php echo esc_attr( $mandateWarning[ 'classes' ] ); ?>" data-wpm-role-snapshot-status="<?php echo esc_attr( $mandateWarning[ 'role_snapshot_status' ] ); ?>"><p><?php echo esc_html( $mandateWarning[ 'text' ] ); ?></p></div>
		<?php
	}
	?>
</div>
