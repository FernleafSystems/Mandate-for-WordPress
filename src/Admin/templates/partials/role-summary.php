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
			foreach ( $roleSummary[ 'rows' ] as $mandateRole ) {
				?>
				<li>
					<?php echo esc_html( $mandateRole[ 'name' ] ); ?>
					<span class="mandate-role-slug">(<?php echo esc_html( $strings[ 'role_slug_label' ] ); ?> <code><?php echo esc_html( $mandateRole[ 'slug' ] ); ?></code>)</span>
				</li>
				<?php
			}
			?>
		</ul>
		<?php
	}
	?>
</div>
