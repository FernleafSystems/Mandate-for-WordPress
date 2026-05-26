<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

?>
<section id="<?php echo esc_attr( $panel[ 'id' ] ); ?>" class="mandate-capability-panel" role="tabpanel" aria-labelledby="<?php echo esc_attr( $panel[ 'tab_id' ] ); ?>" data-wpm-capability-panel="<?php echo esc_attr( $panel[ 'key' ] ); ?>" data-wpm-capability-source-panel data-wpm-capability-source="<?php echo esc_attr( $panel[ 'key' ] ); ?>">
	<div class="mandate-panel-heading">
		<h3>
			<?php echo esc_html( $panel[ 'label' ] ); ?>
			<span class="mandate-capability-source-count"><?php echo esc_html( (string)$panel[ 'count' ] ); ?></span>
		</h3>
		<p>
			<button type="button" class="button" data-wpm-select-panel data-wpm-select-state="<?php echo esc_attr( $panel[ 'bulk_actions' ][ 'select_all' ][ 'state' ] ); ?>"<?php if ( $panel[ 'bulk_actions' ][ 'select_all' ][ 'disabled' ] ) { ?> disabled="disabled"<?php } ?>><?php echo esc_html( $panel[ 'bulk_actions' ][ 'select_all' ][ 'label' ] ); ?></button>
			<button type="button" class="button" data-wpm-select-panel data-wpm-select-state="<?php echo esc_attr( $panel[ 'bulk_actions' ][ 'deselect_all' ][ 'state' ] ); ?>"<?php if ( $panel[ 'bulk_actions' ][ 'deselect_all' ][ 'disabled' ] ) { ?> disabled="disabled"<?php } ?>><?php echo esc_html( $panel[ 'bulk_actions' ][ 'deselect_all' ][ 'label' ] ); ?></button>
		</p>
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
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered partial templates escape their own scalar output.
				echo $mandateSectionHtml;
			}
		}
		?>
	</div>
</section>
