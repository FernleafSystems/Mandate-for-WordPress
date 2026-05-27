<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div id="mandate-role-summary" class="mandate-role-summary mandate-summary-card">
	<p class="mandate-role-summary-label"><?php echo esc_html( $roleSummary[ 'title' ] ); ?></p>
	<?php
	if ( !$roleSummary[ 'has_roles' ] ) {
		?>
		<p class="description"><?php echo esc_html( $roleSummary[ 'empty_text' ] ); ?></p>
		<?php
	}
	else {
		?>
		<ul>
			<?php
			foreach ( $roleSummary[ 'rows' ] as $mandate_app_security_role ) {
				?>
				<li>
					<?php echo esc_html( $mandate_app_security_role[ 'name' ] ); ?>
					<span class="mandate-role-slug">(<?php echo esc_html( $strings[ 'role_slug_label' ] ); ?> <code><?php echo esc_html( $mandate_app_security_role[ 'slug' ] ); ?></code>)</span>
				</li>
				<?php
			}
			?>
		</ul>
		<?php
	}
	?>
</div>
