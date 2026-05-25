<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="<?php echo esc_attr( $classes[ 'root' ] ); ?>">
	<h1><?php echo esc_html( $strings[ 'page_title' ] ); ?></h1>
	<?php
	$mandateMessageHtml = $this->render( 'partials/notice.php', [ 'notice' => $vars[ 'message' ] ] );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered partial templates escape their own scalar output.
	echo $mandateMessageHtml;
	$mandateSelectionHtml = $this->render( 'partials/selection-form.php', [
		'hrefs'         => $hrefs,
		'strings'       => $strings,
		'flags'         => $flags,
		'selectionForm' => $vars[ 'selection_form' ],
		'content'       => $content,
	] );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered partial templates escape their own scalar output.
	echo $mandateSelectionHtml;
	$mandatePageNoticeHtml = $this->render( 'partials/notice.php', [ 'notice' => $vars[ 'page_notice' ] ] );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered partial templates escape their own scalar output.
	echo $mandatePageNoticeHtml;
	?>
	<?php
	if ( $flags[ 'show_scope_form' ] ) {
		$mandateScopeHtml = $this->render( 'partials/scope-form.php', [
			'hrefs'     => $hrefs,
			'scopeForm' => $vars[ 'scope_form' ],
			'content'   => $content,
		] );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered partial templates escape their own scalar output.
		echo $mandateScopeHtml;
	}
	?>
</div>
