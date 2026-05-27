<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

?>
<h2><?php echo esc_html( $scopeForm[ 'heading' ] ); ?></h2>
<?php
$mandate_app_security_super_admin_notice_html = $this->render( 'partials/notice.php', [ 'notice' => $scopeForm[ 'super_admin_notice' ] ] );
echo wp_kses( $mandate_app_security_super_admin_notice_html, $this->allowedAdminHtml() );
$mandate_app_security_lock_notice_html = $this->render( 'partials/notice.php', [ 'notice' => $scopeForm[ 'lock_notice' ] ] );
echo wp_kses( $mandate_app_security_lock_notice_html, $this->allowedAdminHtml() );
?>
<form method="post" action="<?php echo esc_url( $hrefs[ 'scope_form_action' ] ); ?>" id="<?php echo esc_attr( $scopeForm[ 'id' ] ); ?>" class="mandate-scope-form" data-wpm-admin-lock-status="<?php echo esc_attr( $scopeForm[ 'admin_lock_status' ] ); ?>">
	<?php
	echo wp_kses( $trustedHtml[ 'scope_nonce_fields' ], $this->allowedAdminHtml() );
	?>
	<input type="hidden" name="user_id" value="<?php echo esc_attr( $scopeForm[ 'user_id' ] ); ?>" />
	<input type="hidden" name="app_password_uuid" value="<?php echo esc_attr( $scopeForm[ 'uuid' ] ); ?>" />

	<fieldset class="mandate-grouping-controls" data-wpm-capability-grouping>
		<legend><?php echo esc_html( $scopeForm[ 'grouping' ][ 'label' ] ); ?></legend>
		<?php
		foreach ( $scopeForm[ 'grouping' ][ 'modes' ] as $mandate_app_security_grouping_mode ) {
			?>
			<label>
				<input type="radio" name="capability_grouping_mode" value="<?php echo esc_attr( $mandate_app_security_grouping_mode[ 'key' ] ); ?>" data-wpm-capability-grouping-mode<?php if ( $mandate_app_security_grouping_mode[ 'checked' ] ) { ?> checked="checked"<?php } ?> />
				<?php echo esc_html( $mandate_app_security_grouping_mode[ 'label' ] ); ?>
			</label>
			<?php
		}
		?>
	</fieldset>
	<div class="mandate-capability-source-tabs" role="tablist" data-wpm-capability-source-tabs>
		<?php
		foreach ( $scopeForm[ 'source_tabs' ] as $mandate_app_security_source_tab ) {
			?>
			<button type="button" id="<?php echo esc_attr( $mandate_app_security_source_tab[ 'id' ] ); ?>" class="mandate-capability-source-tab<?php if ( $mandate_app_security_source_tab[ 'selected' ] ) { ?> is-active<?php } ?>" role="tab" aria-selected="<?php echo esc_attr( $mandate_app_security_source_tab[ 'selected' ] ? 'true' : 'false' ); ?>" aria-controls="<?php echo esc_attr( $mandate_app_security_source_tab[ 'panel_id' ] ); ?>" data-wpm-capability-source-tab data-wpm-capability-source="<?php echo esc_attr( $mandate_app_security_source_tab[ 'key' ] ); ?>">
				<span><?php echo esc_html( $mandate_app_security_source_tab[ 'label' ] ); ?></span>
				<span class="mandate-capability-source-count"><?php echo esc_html( (string)$mandate_app_security_source_tab[ 'count' ] ); ?></span>
			</button>
			<?php
		}
		?>
	</div>
	<div class="mandate-capability-groups" data-wpm-capability-groups data-wpm-capability-source="<?php echo esc_attr( $scopeForm[ 'grouping' ][ 'default_source' ] ); ?>" data-wpm-capability-mode="<?php echo esc_attr( $scopeForm[ 'grouping' ][ 'default_mode' ] ); ?>" data-wpm-capability-grouping-config="<?php echo esc_attr( $scopeForm[ 'grouping' ][ 'config_json' ] ); ?>">
		<?php
		foreach ( $scopeForm[ 'source_panels' ] as $mandate_app_security_panel ) {
			$mandate_app_security_panel_html = $this->render( 'partials/capability-panel.php', [ 'panel' => $mandate_app_security_panel ] );
			echo wp_kses( $mandate_app_security_panel_html, $this->allowedAdminHtml() );
		}
		?>
	</div>

	<p class="submit mandate-actions">
		<?php
		foreach ( $scopeForm[ 'actions' ] as $mandate_app_security_action ) {
			?>
			<button type="submit" class="<?php echo esc_attr( $mandate_app_security_action[ 'classes' ] ); ?>" name="<?php echo esc_attr( $mandate_app_security_action[ 'name' ] ); ?>" value="<?php echo esc_attr( $mandate_app_security_action[ 'value' ] ); ?>"<?php if ( $mandate_app_security_action[ 'disabled' ] ) { ?> disabled="disabled"<?php } ?>><?php echo esc_html( $mandate_app_security_action[ 'label' ] ); ?></button>
			<?php
		}
		?>
	</p>
</form>
