<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Templates run inside AdminTemplateRenderer::render(), so these are local view variables.

$mdpsc_panel = $mdpscTemplateData[ 'panel' ];

?>
<section id="<?php echo esc_attr( $mdpsc_panel[ 'id' ] ); ?>" class="mdpsc-capability-panel" role="tabpanel" aria-labelledby="<?php echo esc_attr( $mdpsc_panel[ 'tab_id' ] ); ?>" data-mdpsc-capability-panel="<?php echo esc_attr( $mdpsc_panel[ 'key' ] ); ?>" data-mdpsc-capability-source-panel data-mdpsc-capability-source="<?php echo esc_attr( $mdpsc_panel[ 'key' ] ); ?>">
	<div class="mdpsc-capability-toolbar">
		<nav class="mdpsc-capability-section-index" aria-label="<?php echo esc_attr__( 'Capability groups', 'mandate-app-security' ); ?>" data-mdpsc-capability-section-index>
			<?php
			foreach ( $mdpsc_panel[ 'section_index' ] as $mdpsc_index_item ) {
				?>
				<a href="#<?php echo esc_attr( $mdpsc_index_item[ 'target_id' ] ); ?>" data-mdpsc-capability-index-link data-mdpsc-capability-section-target="<?php echo esc_attr( $mdpsc_index_item[ 'target_id' ] ); ?>">
					<span><?php echo esc_html( $mdpsc_index_item[ 'label' ] ); ?></span>
					<span class="mdpsc-capability-section-count"><?php echo esc_html( (string)$mdpsc_index_item[ 'count' ] ); ?></span>
				</a>
				<?php
			}
			?>
		</nav>
	</div>
	<div class="mdpsc-capability-scroll">
		<?php
		if ( $mdpsc_panel[ 'is_empty' ] ) {
			?>
			<p class="description"><?php echo esc_html( $mdpsc_panel[ 'empty_text' ] ); ?></p>
			<?php
		}
		else {
			foreach ( $mdpsc_panel[ 'sections' ] as $mdpsc_section ) {
				$mdpsc_section_html = $this->render( 'partials/capability-section.php', [ 'section' => $mdpsc_section ] );
				echo wp_kses( $mdpsc_section_html, $this->allowedAdminHtml() );
			}
		}
		?>
	</div>
	<p class="mdpsc-panel-actions">
		<button type="button" class="button" data-mdpsc-select-panel data-mdpsc-select-state="<?php echo esc_attr( $mdpsc_panel[ 'bulk_actions' ][ 'select_all' ][ 'state' ] ); ?>"<?php if ( $mdpsc_panel[ 'bulk_actions' ][ 'select_all' ][ 'disabled' ] ) { ?> disabled="disabled"<?php } ?>><?php echo esc_html( $mdpsc_panel[ 'bulk_actions' ][ 'select_all' ][ 'label' ] ); ?></button>
		<button type="button" class="button" data-mdpsc-select-panel data-mdpsc-select-state="<?php echo esc_attr( $mdpsc_panel[ 'bulk_actions' ][ 'deselect_all' ][ 'state' ] ); ?>"<?php if ( $mdpsc_panel[ 'bulk_actions' ][ 'deselect_all' ][ 'disabled' ] ) { ?> disabled="disabled"<?php } ?>><?php echo esc_html( $mdpsc_panel[ 'bulk_actions' ][ 'deselect_all' ][ 'label' ] ); ?></button>
	</p>
</section>
