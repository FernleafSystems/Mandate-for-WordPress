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
$mandateLockNoticeHtml = $this->render( 'partials/notice.php', [ 'notice' => $scopeForm[ 'lock_notice' ] ] );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered partial templates escape their own scalar output.
echo $mandateLockNoticeHtml;
?>
<form method="post" action="<?php echo esc_url( $hrefs[ 'scope_form_action' ] ); ?>" id="<?php echo esc_attr( $scopeForm[ 'id' ] ); ?>" class="mandate-scope-form" data-wpm-admin-lock-status="<?php echo esc_attr( $scopeForm[ 'admin_lock_status' ] ); ?>">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized trusted nonce field HTML produced by the view-data builder.
	echo $trustedHtml[ 'scope_nonce_fields' ];
	?>
	<input type="hidden" name="user_id" value="<?php echo esc_attr( $scopeForm[ 'user_id' ] ); ?>" />
	<input type="hidden" name="app_password_uuid" value="<?php echo esc_attr( $scopeForm[ 'uuid' ] ); ?>" />

	<fieldset class="mandate-grouping-controls" data-wpm-capability-grouping>
		<legend><?php echo esc_html( $scopeForm[ 'grouping' ][ 'label' ] ); ?></legend>
		<?php
		foreach ( $scopeForm[ 'grouping' ][ 'modes' ] as $mandateGroupingMode ) {
			?>
			<label>
				<input type="radio" name="capability_grouping_mode" value="<?php echo esc_attr( $mandateGroupingMode[ 'key' ] ); ?>" data-wpm-capability-grouping-mode<?php if ( $mandateGroupingMode[ 'checked' ] ) { ?> checked="checked"<?php } ?> />
				<?php echo esc_html( $mandateGroupingMode[ 'label' ] ); ?>
			</label>
			<?php
		}
		?>
	</fieldset>
	<div class="mandate-capability-source-tabs" role="tablist" data-wpm-capability-source-tabs>
		<?php
		foreach ( $scopeForm[ 'source_tabs' ] as $mandateSourceTab ) {
			?>
			<button type="button" id="<?php echo esc_attr( $mandateSourceTab[ 'id' ] ); ?>" class="mandate-capability-source-tab<?php if ( $mandateSourceTab[ 'selected' ] ) { ?> is-active<?php } ?>" role="tab" aria-selected="<?php echo esc_attr( $mandateSourceTab[ 'selected' ] ? 'true' : 'false' ); ?>" aria-controls="<?php echo esc_attr( $mandateSourceTab[ 'panel_id' ] ); ?>" data-wpm-capability-source-tab data-wpm-capability-source="<?php echo esc_attr( $mandateSourceTab[ 'key' ] ); ?>">
				<span><?php echo esc_html( $mandateSourceTab[ 'label' ] ); ?></span>
				<span class="mandate-capability-source-count"><?php echo esc_html( (string)$mandateSourceTab[ 'count' ] ); ?></span>
			</button>
			<?php
		}
		?>
	</div>
	<div class="mandate-capability-groups" data-wpm-capability-groups data-wpm-capability-source="<?php echo esc_attr( $scopeForm[ 'grouping' ][ 'default_source' ] ); ?>" data-wpm-capability-mode="<?php echo esc_attr( $scopeForm[ 'grouping' ][ 'default_mode' ] ); ?>" data-wpm-capability-grouping-config="<?php echo esc_attr( $scopeForm[ 'grouping' ][ 'config_json' ] ); ?>">
		<?php
		foreach ( $scopeForm[ 'source_panels' ] as $mandatePanel ) {
			$mandatePanelHtml = $this->render( 'partials/capability-panel.php', [ 'panel' => $mandatePanel ] );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered partial templates escape their own scalar output.
			echo $mandatePanelHtml;
		}
		?>
	</div>

	<?php
	if ( $scopeForm[ 'admin_lock' ][ 'is_visible' ] ) {
		?>
		<p class="mandate-admin-lock">
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $scopeForm[ 'admin_lock' ][ 'name' ] ); ?>" value="<?php echo esc_attr( $scopeForm[ 'admin_lock' ][ 'value' ] ); ?>"<?php if ( $scopeForm[ 'admin_lock' ][ 'checked' ] ) { ?> checked="checked"<?php } ?><?php if ( $scopeForm[ 'admin_lock' ][ 'disabled' ] ) { ?> disabled="disabled"<?php } ?> />
				<?php echo esc_html( $scopeForm[ 'admin_lock' ][ 'label' ] ); ?>
			</label>
		</p>
		<?php
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
