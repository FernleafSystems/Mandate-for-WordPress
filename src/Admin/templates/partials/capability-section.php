<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

?>
<fieldset id="<?php echo esc_attr( $section[ 'id' ] ); ?>" class="mandate-capability-section" data-wpm-capability-section>
	<legend>
		<span class="mandate-capability-section-title">
			<span><?php echo esc_html( $section[ 'label' ] ); ?></span>
			<span class="mandate-capability-section-count"><?php echo esc_html( (string)$section[ 'count' ] ); ?></span>
		</span>
		<span class="mandate-capability-section-actions">
			<button type="button" class="mandate-link-button" data-wpm-select-section data-wpm-select-state="<?php echo esc_attr( $section[ 'bulk_actions' ][ 'select_all' ][ 'state' ] ); ?>"<?php if ( $section[ 'bulk_actions' ][ 'select_all' ][ 'disabled' ] ) { ?> disabled="disabled"<?php } ?>><?php echo esc_html( $section[ 'bulk_actions' ][ 'select_all' ][ 'label' ] ); ?></button>
			<span class="mandate-capability-section-action-separator" aria-hidden="true">/</span>
			<button type="button" class="mandate-link-button" data-wpm-select-section data-wpm-select-state="<?php echo esc_attr( $section[ 'bulk_actions' ][ 'deselect_all' ][ 'state' ] ); ?>"<?php if ( $section[ 'bulk_actions' ][ 'deselect_all' ][ 'disabled' ] ) { ?> disabled="disabled"<?php } ?>><?php echo esc_html( $section[ 'bulk_actions' ][ 'deselect_all' ][ 'label' ] ); ?></button>
		</span>
	</legend>
	<div class="mandate-capability-list">
		<?php
		foreach ( $section[ 'items' ] as $mandateItem ) {
			$mandateItemHtml = $this->render( 'partials/capability-item.php', [ 'item' => $mandateItem ] );
			echo wp_kses( $mandateItemHtml, $this->allowedAdminHtml() );
		}
		?>
	</div>
</fieldset>
