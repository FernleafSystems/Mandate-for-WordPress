<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

?>
<form method="get" action="<?php echo esc_url( $hrefs[ 'selection_form_action' ] ); ?>" class="mandate-selection" data-wpm-selection-form>
	<input type="hidden" name="page" value="<?php echo esc_attr( $selectionForm[ 'page_slug' ] ); ?>" />
	<div class="mandate-selection-grid">
		<div class="mandate-selection-column">
			<div class="mandate-field">
				<label class="mandate-field-title" for="mandate-user"><?php echo esc_html( $strings[ 'user_label' ] ); ?></label>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted user dropdown HTML produced by the view-data builder.
				echo $content[ 'user_dropdown' ];
				?>
			</div>
			<?php
			$mandateRoleSummaryHtml = $this->render( 'partials/role-summary.php', [
				'roleSummary' => $selectionForm[ 'role_summary' ],
				'strings'     => $strings,
			] );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered partial templates escape their own scalar output.
			echo $mandateRoleSummaryHtml;
			?>
		</div>

		<div class="mandate-selection-column">
			<div class="mandate-field">
				<label class="mandate-field-title" for="mandate-password"><?php echo esc_html( $strings[ 'application_password_label' ] ); ?></label>
				<?php
				if ( $flags[ 'has_passwords' ] ) {
					?>
					<select id="mandate-password" name="app_password_uuid">
						<?php
						foreach ( $selectionForm[ 'password_options' ] as $mandateOption ) {
							?>
							<option value="<?php echo esc_attr( $mandateOption[ 'uuid' ] ); ?>"<?php if ( $mandateOption[ 'selected' ] ) { ?> selected="selected"<?php } ?>><?php echo esc_html( $mandateOption[ 'name' ] ); ?></option>
							<?php
						}
						?>
					</select>
					<?php
				}
				else {
					?>
					<p class="description"><?php echo esc_html( $strings[ 'no_application_passwords_available' ] ); ?></p>
					<?php
				}
				?>
			</div>
		</div>

		<div class="mandate-selection-column">
			<?php
			$mandatePasswordSummaryHtml = $this->render( 'partials/password-summary.php', [ 'summary' => $selectionForm[ 'password_summary' ] ] );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered partial templates escape their own scalar output.
			echo $mandatePasswordSummaryHtml;
			?>
		</div>
	</div>
	<p class="mandate-selection-status" data-wpm-selection-status hidden><?php echo esc_html( $strings[ 'loading_selection' ] ); ?></p>
	<noscript><p class="mandate-selection-fallback"><button type="submit" class="button button-secondary"><?php echo esc_html( $strings[ 'apply_selection' ] ); ?></button></p></noscript>
</form>
