<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Templates run inside AdminTemplateRenderer::render(), so these are local view variables.

$mdpsc_classes = $mdpscTemplateData[ 'classes' ];
$mdpsc_hrefs = $mdpscTemplateData[ 'hrefs' ];
$mdpsc_strings = $mdpscTemplateData[ 'strings' ];
$mdpsc_flags = $mdpscTemplateData[ 'flags' ];
$mdpsc_vars = $mdpscTemplateData[ 'vars' ];
$mdpsc_trusted_html = $mdpscTemplateData[ 'trustedHtml' ];

?>
<div class="<?php echo esc_attr( $mdpsc_classes[ 'root' ] ); ?>">
	<h1><?php echo esc_html( $mdpsc_strings[ 'page_title' ] ); ?></h1>
	<?php
	$mdpsc_message_html = $this->render( 'partials/notice.php', [ 'notice' => $mdpsc_vars[ 'message' ] ] );
	echo wp_kses( $mdpsc_message_html, $this->allowedAdminHtml() );
	$mdpsc_selection_html = $this->render( 'partials/selection-form.php', [
		'hrefs'         => $mdpsc_hrefs,
		'strings'       => $mdpsc_strings,
		'flags'         => $mdpsc_flags,
		'selectionForm' => $mdpsc_vars[ 'selection_form' ],
		'trustedHtml'   => $mdpsc_trusted_html,
	] );
	echo wp_kses( $mdpsc_selection_html, $this->allowedAdminHtml() );
	$mdpsc_page_notice_html = $this->render( 'partials/notice.php', [ 'notice' => $mdpsc_vars[ 'page_notice' ] ] );
	echo wp_kses( $mdpsc_page_notice_html, $this->allowedAdminHtml() );
	?>
	<?php
	if ( $mdpsc_flags[ 'show_scope_form' ] ) {
		$mdpsc_scope_html = $this->render( 'partials/scope-form.php', [
			'hrefs'       => $mdpsc_hrefs,
			'scopeForm'   => $mdpsc_vars[ 'scope_form' ],
			'trustedHtml' => $mdpsc_trusted_html,
		] );
		echo wp_kses( $mdpsc_scope_html, $this->allowedAdminHtml() );
	}
	?>
</div>
