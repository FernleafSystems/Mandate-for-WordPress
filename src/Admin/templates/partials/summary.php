<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Templates run inside AdminTemplateRenderer::render(), so these are local view variables.

$mdpsc_summary = $mdpscTemplateData[ 'summary' ];

if ( !$mdpsc_summary[ 'is_visible' ] ) {
	return;
}
?>
<?php if ( $mdpsc_summary[ 'title_placement' ] === 'outside' ) { ?>
	<p id="<?php echo esc_attr( $mdpsc_summary[ 'title_id' ] ); ?>" class="mdpsc-field-title"><?php echo esc_html( $mdpsc_summary[ 'title' ] ); ?></p>
<?php } ?>
<div id="<?php echo esc_attr( $mdpsc_summary[ 'container_id' ] ); ?>" class="mdpsc-detail-summary mdpsc-summary-card" aria-labelledby="<?php echo esc_attr( $mdpsc_summary[ 'title_id' ] ); ?>">
	<?php if ( $mdpsc_summary[ 'title_placement' ] === 'inside' ) { ?>
		<p id="<?php echo esc_attr( $mdpsc_summary[ 'title_id' ] ); ?>" class="mdpsc-summary-title"><?php echo esc_html( $mdpsc_summary[ 'title' ] ); ?></p>
	<?php } ?>
	<dl class="mdpsc-summary-details">
		<?php
		foreach ( $mdpsc_summary[ 'details' ] as $mdpsc_detail ) {
			$mdpsc_detail_html = $this->render( 'partials/summary-detail.php', [ 'detail' => $mdpsc_detail ] );
			echo wp_kses( $mdpsc_detail_html, $this->allowedAdminHtml() );
		}
		?>
	</dl>
	<?php
	foreach ( $mdpsc_summary[ 'warnings' ] as $mdpsc_warning ) {
		?>
		<div class="<?php echo esc_attr( $mdpsc_warning[ 'classes' ] ); ?>" data-mdpsc-role-snapshot-status="<?php echo esc_attr( $mdpsc_warning[ 'role_snapshot_status' ] ); ?>"><p><?php echo esc_html( $mdpsc_warning[ 'text' ] ); ?></p></div>
		<?php
	}
	?>
</div>
