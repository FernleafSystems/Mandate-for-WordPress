<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

?>
<fieldset id="<?php echo esc_attr( $section[ 'id' ] ); ?>" class="mandate-capability-section">
	<legend>
		<span><?php echo esc_html( $section[ 'label' ] ); ?></span>
		<span class="mandate-capability-section-count"><?php echo esc_html( (string)$section[ 'count' ] ); ?></span>
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
