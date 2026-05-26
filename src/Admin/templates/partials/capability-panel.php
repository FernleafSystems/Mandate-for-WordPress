<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

?>
<section id="<?php echo esc_attr( $panel[ 'id' ] ); ?>" class="mandate-capability-panel" data-wpm-panel="<?php echo esc_attr( $panel[ 'key' ] ); ?>" aria-labelledby="<?php echo esc_attr( $panel[ 'tab_id' ] ); ?>">
	<div class="mandate-panel-heading">
		<p>
			<button type="button" class="button" data-wpm-select-group="<?php echo esc_attr( $panel[ 'key' ] ); ?>" data-wpm-select-state="<?php echo esc_attr( $panel[ 'bulk_actions' ][ 'select_all' ][ 'state' ] ); ?>"><?php echo esc_html( $panel[ 'bulk_actions' ][ 'select_all' ][ 'label' ] ); ?></button>
			<button type="button" class="button" data-wpm-select-group="<?php echo esc_attr( $panel[ 'key' ] ); ?>" data-wpm-select-state="<?php echo esc_attr( $panel[ 'bulk_actions' ][ 'deselect_all' ][ 'state' ] ); ?>"><?php echo esc_html( $panel[ 'bulk_actions' ][ 'deselect_all' ][ 'label' ] ); ?></button>
		</p>
	</div>
	<div class="mandate-capability-scroll">
		<?php
		foreach ( $panel[ 'sections' ] as $mandateSection ) {
			$mandateSectionHtml = $this->render( 'partials/capability-section.php', [ 'section' => $mandateSection ] );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered partial templates escape their own scalar output.
			echo $mandateSectionHtml;
		}
		?>
	</div>
</section>
