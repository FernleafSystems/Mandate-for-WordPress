<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="<?php echo esc_attr( $classes[ 'root' ] ); ?>">
	<h1><?php echo esc_html( $strings[ 'page_title' ] ); ?></h1>
	<?php
	$mandate_app_security_message_html = $this->render( 'partials/notice.php', [ 'notice' => $vars[ 'message' ] ] );
	echo wp_kses( $mandate_app_security_message_html, $this->allowedAdminHtml() );
	$mandate_app_security_selection_html = $this->render( 'partials/selection-form.php', [
		'hrefs'         => $hrefs,
		'strings'       => $strings,
		'flags'         => $flags,
		'selectionForm' => $vars[ 'selection_form' ],
		'trustedHtml'   => $trustedHtml,
	] );
	echo wp_kses( $mandate_app_security_selection_html, $this->allowedAdminHtml() );
	$mandate_app_security_page_notice_html = $this->render( 'partials/notice.php', [ 'notice' => $vars[ 'page_notice' ] ] );
	echo wp_kses( $mandate_app_security_page_notice_html, $this->allowedAdminHtml() );
	?>
	<?php
	if ( $flags[ 'show_scope_form' ] ) {
		$mandate_app_security_scope_html = $this->render( 'partials/scope-form.php', [
			'hrefs'     => $hrefs,
			'scopeForm' => $vars[ 'scope_form' ],
			'trustedHtml' => $trustedHtml,
		] );
		echo wp_kses( $mandate_app_security_scope_html, $this->allowedAdminHtml() );
	}
	?>
</div>
