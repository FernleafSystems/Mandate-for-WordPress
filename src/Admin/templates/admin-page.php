<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="<?php echo esc_attr( $classes[ 'root' ] ); ?>">
	<h1><?php echo esc_html( $strings[ 'page_title' ] ); ?></h1>
	<?php
	$mandateMessageHtml = $this->render( 'partials/notice.php', [ 'notice' => $vars[ 'message' ] ] );
	echo wp_kses( $mandateMessageHtml, $this->allowedAdminHtml() );
	$mandateSelectionHtml = $this->render( 'partials/selection-form.php', [
		'hrefs'         => $hrefs,
		'strings'       => $strings,
		'flags'         => $flags,
		'selectionForm' => $vars[ 'selection_form' ],
		'trustedHtml'   => $trustedHtml,
	] );
	echo wp_kses( $mandateSelectionHtml, $this->allowedAdminHtml() );
	$mandatePageNoticeHtml = $this->render( 'partials/notice.php', [ 'notice' => $vars[ 'page_notice' ] ] );
	echo wp_kses( $mandatePageNoticeHtml, $this->allowedAdminHtml() );
	?>
	<?php
	if ( $flags[ 'show_scope_form' ] ) {
		$mandateScopeHtml = $this->render( 'partials/scope-form.php', [
			'hrefs'     => $hrefs,
			'scopeForm' => $vars[ 'scope_form' ],
			'trustedHtml' => $trustedHtml,
		] );
		echo wp_kses( $mandateScopeHtml, $this->allowedAdminHtml() );
	}
	?>
</div>
