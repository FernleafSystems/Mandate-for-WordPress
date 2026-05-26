<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

?>
<h2><?php echo esc_html( $scopeForm[ 'heading' ] ); ?></h2>
<?php
$mandateSuperAdminNoticeHtml = $this->render( 'partials/notice.php', [ 'notice' => $scopeForm[ 'super_admin_notice' ] ] );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered partial templates escape their own scalar output.
echo $mandateSuperAdminNoticeHtml;
?>
<form method="post" action="<?php echo esc_url( $hrefs[ 'scope_form_action' ] ); ?>" id="<?php echo esc_attr( $scopeForm[ 'id' ] ); ?>" class="mandate-scope-form">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized trusted nonce field HTML produced by the view-data builder.
	echo $trustedHtml[ 'scope_nonce_fields' ];
	?>
	<input type="hidden" name="user_id" value="<?php echo esc_attr( $scopeForm[ 'user_id' ] ); ?>" />
	<input type="hidden" name="app_password_uuid" value="<?php echo esc_attr( $scopeForm[ 'uuid' ] ); ?>" />

	<div class="mandate-tabs" role="tablist" aria-label="<?php echo esc_attr( $scopeForm[ 'tablist_label' ] ); ?>">
		<?php
		foreach ( $scopeForm[ 'tabs' ] as $mandateTab ) {
			?>
			<button type="button" id="<?php echo esc_attr( $mandateTab[ 'id' ] ); ?>" class="<?php echo esc_attr( $mandateTab[ 'classes' ] ); ?>" role="tab" data-wpm-tab="<?php echo esc_attr( $mandateTab[ 'key' ] ); ?>" aria-controls="<?php echo esc_attr( $mandateTab[ 'panel_id' ] ); ?>" aria-selected="<?php echo esc_attr( $mandateTab[ 'aria_selected' ] ); ?>"><?php echo esc_html( $mandateTab[ 'label' ] ); ?></button>
			<?php
		}
		?>
	</div>

	<?php
	foreach ( $scopeForm[ 'panels' ] as $mandatePanel ) {
		$mandatePanelHtml = $this->render( 'partials/capability-panel.php', [ 'panel' => $mandatePanel ] );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered partial templates escape their own scalar output.
		echo $mandatePanelHtml;
	}
	?>

	<p class="submit mandate-actions">
		<?php
		foreach ( $scopeForm[ 'actions' ] as $mandateAction ) {
			?>
			<button type="submit" class="<?php echo esc_attr( $mandateAction[ 'classes' ] ); ?>" name="<?php echo esc_attr( $mandateAction[ 'name' ] ); ?>" value="<?php echo esc_attr( $mandateAction[ 'value' ] ); ?>"<?php if ( $mandateAction[ 'disabled' ] ) { ?> disabled="disabled"<?php } ?>><?php echo esc_html( $mandateAction[ 'label' ] ); ?></button>
			<?php
		}
		?>
	</p>
</form>
