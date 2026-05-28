<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Templates run inside AdminTemplateRenderer::render(), so these are local view variables.

$mdpsc_role_summary = $mdpscTemplateData[ 'roleSummary' ];
$mdpsc_strings = $mdpscTemplateData[ 'strings' ];

?>
<div id="mdpsc-role-summary" class="mdpsc-role-summary mdpsc-summary-card">
	<p class="mdpsc-role-summary-label"><?php echo esc_html( $mdpsc_role_summary[ 'title' ] ); ?></p>
	<?php
	if ( !$mdpsc_role_summary[ 'has_roles' ] ) {
		?>
		<p class="description"><?php echo esc_html( $mdpsc_role_summary[ 'empty_text' ] ); ?></p>
		<?php
	}
	else {
		?>
		<ul>
			<?php
			foreach ( $mdpsc_role_summary[ 'rows' ] as $mdpsc_role ) {
				?>
				<li>
					<?php echo esc_html( $mdpsc_role[ 'name' ] ); ?>
					<span class="mdpsc-role-slug">(<?php echo esc_html( $mdpsc_strings[ 'role_slug_label' ] ); ?> <code><?php echo esc_html( $mdpsc_role[ 'slug' ] ); ?></code>)</span>
				</li>
				<?php
			}
			?>
		</ul>
		<?php
	}
	?>
</div>
