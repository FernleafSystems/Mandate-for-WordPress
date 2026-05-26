<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

?>
<section id="<?php echo esc_attr( $panel[ 'id' ] ); ?>" class="mandate-capability-panel" role="tabpanel" aria-labelledby="<?php echo esc_attr( $panel[ 'tab_id' ] ); ?>" data-wpm-capability-panel="<?php echo esc_attr( $panel[ 'key' ] ); ?>" data-wpm-capability-source-panel data-wpm-capability-source="<?php echo esc_attr( $panel[ 'key' ] ); ?>">
	<div class="mandate-capability-toolbar">
		<nav class="mandate-capability-section-index" aria-label="<?php echo esc_attr__( 'Capability groups', 'mandate-app-security' ); ?>" data-wpm-capability-section-index>
			<?php
			foreach ( $panel[ 'section_index' ] as $mandateIndexItem ) {
				?>
				<a href="#<?php echo esc_attr( $mandateIndexItem[ 'target_id' ] ); ?>" data-wpm-capability-index-link data-wpm-capability-section-target="<?php echo esc_attr( $mandateIndexItem[ 'target_id' ] ); ?>">
					<span><?php echo esc_html( $mandateIndexItem[ 'label' ] ); ?></span>
					<span class="mandate-capability-section-count"><?php echo esc_html( (string)$mandateIndexItem[ 'count' ] ); ?></span>
				</a>
				<?php
			}
			?>
		</nav>
	</div>
	<div class="mandate-capability-scroll">
		<?php
		if ( $panel[ 'is_empty' ] ) {
			?>
			<p class="description"><?php echo esc_html( $panel[ 'empty_text' ] ); ?></p>
			<?php
		}
		else {
			foreach ( $panel[ 'sections' ] as $mandateSection ) {
				$mandateSectionHtml = $this->render( 'partials/capability-section.php', [ 'section' => $mandateSection ] );
				echo wp_kses( $mandateSectionHtml, $this->allowedAdminHtml() );
			}
		}
		?>
	</div>
	<p class="mandate-panel-actions">
		<button type="button" class="button" data-wpm-select-panel data-wpm-select-state="<?php echo esc_attr( $panel[ 'bulk_actions' ][ 'select_all' ][ 'state' ] ); ?>"<?php if ( $panel[ 'bulk_actions' ][ 'select_all' ][ 'disabled' ] ) { ?> disabled="disabled"<?php } ?>><?php echo esc_html( $panel[ 'bulk_actions' ][ 'select_all' ][ 'label' ] ); ?></button>
		<button type="button" class="button" data-wpm-select-panel data-wpm-select-state="<?php echo esc_attr( $panel[ 'bulk_actions' ][ 'deselect_all' ][ 'state' ] ); ?>"<?php if ( $panel[ 'bulk_actions' ][ 'deselect_all' ][ 'disabled' ] ) { ?> disabled="disabled"<?php } ?>><?php echo esc_html( $panel[ 'bulk_actions' ][ 'deselect_all' ][ 'label' ] ); ?></button>
	</p>
</section>
