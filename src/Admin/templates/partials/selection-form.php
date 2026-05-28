<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Templates run inside AdminTemplateRenderer::render(), so these are local view variables.

$mdpsc_hrefs = $mdpscTemplateData[ 'hrefs' ];
$mdpsc_strings = $mdpscTemplateData[ 'strings' ];
$mdpsc_flags = $mdpscTemplateData[ 'flags' ];
$mdpsc_selection_form = $mdpscTemplateData[ 'selectionForm' ];
$mdpsc_trusted_html = $mdpscTemplateData[ 'trustedHtml' ];

?>
<form method="get" action="<?php echo esc_url( $mdpsc_hrefs[ 'selection_form_action' ] ); ?>" class="mdpsc-selection" data-mdpsc-selection-form>
	<input type="hidden" name="page" value="<?php echo esc_attr( $mdpsc_selection_form[ 'page_slug' ] ); ?>" />
	<div class="mdpsc-selection-grid">
		<div class="mdpsc-selection-column">
			<div class="mdpsc-field">
				<label class="mdpsc-field-title" for="mdpsc-user"><?php echo esc_html( $mdpsc_strings[ 'user_label' ] ); ?></label>
				<?php
				echo wp_kses( $mdpsc_trusted_html[ 'user_dropdown' ], $this->allowedAdminHtml() );
				?>
			</div>
			<?php
			$mdpsc_role_summary_html = $this->render( 'partials/role-summary.php', [
				'roleSummary' => $mdpsc_selection_form[ 'role_summary' ],
				'strings'     => $mdpsc_strings,
			] );
			echo wp_kses( $mdpsc_role_summary_html, $this->allowedAdminHtml() );
			?>
		</div>

		<div class="mdpsc-selection-column">
			<div class="mdpsc-field">
				<label class="mdpsc-field-title" for="mdpsc-password"><?php echo esc_html( $mdpsc_strings[ 'application_password_label' ] ); ?></label>
				<?php
				if ( $mdpsc_flags[ 'has_passwords' ] ) {
					?>
					<select id="mdpsc-password" name="app_password_uuid">
						<?php
						foreach ( $mdpsc_selection_form[ 'password_options' ] as $mdpsc_option ) {
							?>
							<option value="<?php echo esc_attr( $mdpsc_option[ 'uuid' ] ); ?>"<?php if ( $mdpsc_option[ 'selected' ] ) { ?> selected="selected"<?php } ?>><?php echo esc_html( $mdpsc_option[ 'name' ] ); ?></option>
							<?php
						}
						?>
					</select>
					<?php
				}
				else {
					?>
					<p class="description"><?php echo esc_html( $mdpsc_strings[ 'no_application_passwords_available' ] ); ?></p>
					<?php
				}
				?>
			</div>
			<?php
			$mdpsc_password_info_html = $this->render( 'partials/summary.php', [ 'summary' => $mdpsc_selection_form[ 'password_info' ] ] );
			echo wp_kses( $mdpsc_password_info_html, $this->allowedAdminHtml() );
			?>
		</div>

		<div class="mdpsc-selection-column">
			<?php
			$mdpsc_scope_summary_html = $this->render( 'partials/summary.php', [ 'summary' => $mdpsc_selection_form[ 'scope_summary' ] ] );
			echo wp_kses( $mdpsc_scope_summary_html, $this->allowedAdminHtml() );
			?>
		</div>
	</div>
	<p class="mdpsc-selection-status" data-mdpsc-selection-status hidden><?php echo esc_html( $mdpsc_strings[ 'loading_selection' ] ); ?></p>
	<noscript><p class="mdpsc-selection-fallback"><button type="submit" class="button button-secondary"><?php echo esc_html( $mdpsc_strings[ 'apply_selection' ] ); ?></button></p></noscript>
</form>
