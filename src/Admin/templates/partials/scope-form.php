<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Templates run inside AdminTemplateRenderer::render(), so these are local view variables.

$mdpsc_hrefs = $mdpscTemplateData[ 'hrefs' ];
$mdpsc_scope_form = $mdpscTemplateData[ 'scopeForm' ];
$mdpsc_trusted_html = $mdpscTemplateData[ 'trustedHtml' ];

?>
<h2><?php echo esc_html( $mdpsc_scope_form[ 'heading' ] ); ?></h2>
<?php
$mdpsc_super_admin_notice_html = $this->render( 'partials/notice.php', [ 'notice' => $mdpsc_scope_form[ 'super_admin_notice' ] ] );
echo wp_kses( $mdpsc_super_admin_notice_html, $this->allowedAdminHtml() );
$mdpsc_lock_notice_html = $this->render( 'partials/notice.php', [ 'notice' => $mdpsc_scope_form[ 'lock_notice' ] ] );
echo wp_kses( $mdpsc_lock_notice_html, $this->allowedAdminHtml() );
?>
<form method="post" action="<?php echo esc_url( $mdpsc_hrefs[ 'scope_form_action' ] ); ?>" id="<?php echo esc_attr( $mdpsc_scope_form[ 'id' ] ); ?>" class="mdpsc-scope-form" data-mdpsc-admin-lock-status="<?php echo esc_attr( $mdpsc_scope_form[ 'admin_lock_status' ] ); ?>">
	<?php
	echo wp_kses( $mdpsc_trusted_html[ 'scope_nonce_fields' ], $this->allowedAdminHtml() );
	?>
	<input type="hidden" name="user_id" value="<?php echo esc_attr( $mdpsc_scope_form[ 'user_id' ] ); ?>" />
	<input type="hidden" name="app_password_uuid" value="<?php echo esc_attr( $mdpsc_scope_form[ 'uuid' ] ); ?>" />

	<fieldset class="mdpsc-grouping-controls" data-mdpsc-capability-grouping>
		<legend><?php echo esc_html( $mdpsc_scope_form[ 'grouping' ][ 'label' ] ); ?></legend>
		<?php
		foreach ( $mdpsc_scope_form[ 'grouping' ][ 'modes' ] as $mdpsc_grouping_mode ) {
			?>
			<label>
				<input type="radio" name="capability_grouping_mode" value="<?php echo esc_attr( $mdpsc_grouping_mode[ 'key' ] ); ?>" data-mdpsc-capability-grouping-mode<?php if ( $mdpsc_grouping_mode[ 'checked' ] ) { ?> checked="checked"<?php } ?> />
				<?php echo esc_html( $mdpsc_grouping_mode[ 'label' ] ); ?>
			</label>
			<?php
		}
		?>
	</fieldset>
	<div class="mdpsc-capability-source-tabs" role="tablist" data-mdpsc-capability-source-tabs>
		<?php
		foreach ( $mdpsc_scope_form[ 'source_tabs' ] as $mdpsc_source_tab ) {
			?>
			<button type="button" id="<?php echo esc_attr( $mdpsc_source_tab[ 'id' ] ); ?>" class="mdpsc-capability-source-tab<?php if ( $mdpsc_source_tab[ 'selected' ] ) { ?> is-active<?php } ?>" role="tab" aria-selected="<?php echo esc_attr( $mdpsc_source_tab[ 'selected' ] ? 'true' : 'false' ); ?>" aria-controls="<?php echo esc_attr( $mdpsc_source_tab[ 'panel_id' ] ); ?>" data-mdpsc-capability-source-tab data-mdpsc-capability-source="<?php echo esc_attr( $mdpsc_source_tab[ 'key' ] ); ?>">
				<span><?php echo esc_html( $mdpsc_source_tab[ 'label' ] ); ?></span>
				<span class="mdpsc-capability-source-count"><?php echo esc_html( (string)$mdpsc_source_tab[ 'count' ] ); ?></span>
			</button>
			<?php
		}
		?>
	</div>
	<div class="mdpsc-capability-groups" data-mdpsc-capability-groups data-mdpsc-capability-source="<?php echo esc_attr( $mdpsc_scope_form[ 'grouping' ][ 'default_source' ] ); ?>" data-mdpsc-capability-mode="<?php echo esc_attr( $mdpsc_scope_form[ 'grouping' ][ 'default_mode' ] ); ?>" data-mdpsc-capability-grouping-config="<?php echo esc_attr( $mdpsc_scope_form[ 'grouping' ][ 'config_json' ] ); ?>">
		<?php
		foreach ( $mdpsc_scope_form[ 'source_panels' ] as $mdpsc_panel ) {
			$mdpsc_panel_html = $this->render( 'partials/capability-panel.php', [ 'panel' => $mdpsc_panel ] );
			echo wp_kses( $mdpsc_panel_html, $this->allowedAdminHtml() );
		}
		?>
	</div>

	<p class="submit mdpsc-actions">
		<?php
		foreach ( $mdpsc_scope_form[ 'actions' ] as $mdpsc_action ) {
			?>
			<button type="submit" class="<?php echo esc_attr( $mdpsc_action[ 'classes' ] ); ?>" name="<?php echo esc_attr( $mdpsc_action[ 'name' ] ); ?>" value="<?php echo esc_attr( $mdpsc_action[ 'value' ] ); ?>"<?php if ( $mdpsc_action[ 'disabled' ] ) { ?> disabled="disabled"<?php } ?>><?php echo esc_html( $mdpsc_action[ 'label' ] ); ?></button>
			<?php
		}
		?>
	</p>
</form>
