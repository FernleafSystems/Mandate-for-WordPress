<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Admin;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class AdminTemplateRenderer {

	private const ALLOWED_TEMPLATES = [
		'admin-page.php' => true,
		'partials/capability-item.php' => true,
		'partials/capability-panel.php' => true,
		'partials/capability-section.php' => true,
		'partials/notice.php' => true,
		'partials/role-summary.php' => true,
		'partials/scope-form.php' => true,
		'partials/selection-form.php' => true,
		'partials/summary-detail.php' => true,
		'partials/summary.php' => true,
	];

	private string $baseDirectory;

	public function __construct() {
		$baseDirectory = realpath( __DIR__.'/templates' );
		if ( $baseDirectory === false || !is_dir( $baseDirectory ) ) {
			throw new \RuntimeException( 'Admin template directory is missing.' );
		}

		$this->baseDirectory = rtrim( $baseDirectory, "\\/" );
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function render( string $template, array $data ) :string {
		$templatePath = $this->resolveTemplatePath( $template );

		ob_start();
		try {
			$mdpscTemplateData = $data;
			require $templatePath;
			return (string)ob_get_clean();
		}
		catch ( \Throwable $throwable ) {
			ob_end_clean();
			throw $throwable;
		}
	}

	/**
	 * @return array<string,array<string,bool>>
	 */
	public function allowedAdminHtml() :array {
		$globalAttributes = [
			'id'                             => true,
			'class'                          => true,
			'hidden'                         => true,
			'tabindex'                       => true,
			'role'                           => true,
			'aria-label'                     => true,
			'aria-hidden'                    => true,
			'aria-labelledby'                => true,
			'aria-selected'                  => true,
			'aria-controls'                  => true,
			'data-mdpsc-admin-lock-input'      => true,
			'data-mdpsc-admin-lock-status'     => true,
			'data-mdpsc-capability-action'     => true,
			'data-mdpsc-capability-area'       => true,
			'data-mdpsc-capability-groups'     => true,
			'data-mdpsc-capability-grouping'   => true,
			'data-mdpsc-capability-grouping-config' => true,
			'data-mdpsc-capability-grouping-mode'   => true,
			'data-mdpsc-capability-index-link' => true,
			'data-mdpsc-capability-item'       => true,
			'data-mdpsc-capability-key'        => true,
			'data-mdpsc-capability-mode'       => true,
			'data-mdpsc-capability-name'       => true,
			'data-mdpsc-capability-panel'      => true,
			'data-mdpsc-capability-section-index'  => true,
			'data-mdpsc-capability-section'    => true,
			'data-mdpsc-capability-section-target' => true,
			'data-mdpsc-capability-source'     => true,
			'data-mdpsc-capability-source-panel' => true,
			'data-mdpsc-capability-source-tab' => true,
			'data-mdpsc-capability-source-tabs' => true,
			'data-mdpsc-capability-type'       => true,
			'data-mdpsc-expiration-input'      => true,
			'data-mdpsc-expiration-state'      => true,
			'data-mdpsc-expiration-summary'    => true,
			'data-mdpsc-role-snapshot-status'  => true,
			'data-mdpsc-select-panel'          => true,
			'data-mdpsc-select-section'        => true,
			'data-mdpsc-select-state'          => true,
			'data-mdpsc-selection-form'        => true,
			'data-mdpsc-selection-status'      => true,
			'data-mdpsc-tooltip'               => true,
			'data-mdpsc-tooltip-text'          => true,
		];

		$tags = [
			'a'        => [ 'href' => true ],
			'button'   => [
				'disabled' => true,
				'name'     => true,
				'type'     => true,
				'value'    => true,
			],
			'code'     => [],
			'dd'       => [],
			'div'      => [],
			'dl'       => [],
			'dt'       => [],
			'fieldset' => [],
			'form'     => [
				'action' => true,
				'method' => true,
			],
			'h1'       => [],
			'h2'       => [],
			'input'    => [
				'checked'  => true,
				'disabled' => true,
				'form'     => true,
				'name'     => true,
				'type'     => true,
				'value'    => true,
			],
			'label'    => [ 'for' => true ],
			'legend'   => [],
			'li'       => [],
			'nav'      => [],
			'noscript' => [],
			'option'   => [
				'disabled' => true,
				'selected' => true,
				'value'    => true,
			],
			'p'        => [],
			'section'  => [],
			'select'   => [
				'disabled' => true,
				'name'     => true,
			],
			'span'     => [],
			'ul'       => [],
		];

		return array_map(
			static fn( array $attributes ) :array => $attributes + $globalAttributes,
			$tags
		);
	}

	private function resolveTemplatePath( string $template ) :string {
		$this->assertAllowedTemplateName( $template );

		$path = realpath( $this->baseDirectory.DIRECTORY_SEPARATOR.str_replace( '/', DIRECTORY_SEPARATOR, $template ) );
		if ( $path === false || !is_file( $path ) ) {
			throw new \RuntimeException( 'Admin template is missing.' );
		}

		$base = $this->normalizePath( $this->baseDirectory );
		$path = $this->normalizePath( $path );
		if ( $path !== $base && !str_starts_with( $path, $base.'/' ) ) {
			throw new \RuntimeException( 'Admin template path escapes the template directory.' );
		}

		return $path;
	}

	private function assertAllowedTemplateName( string $template ) :void {
		if ( $template === '' || $template !== trim( $template ) ) {
			throw new \RuntimeException( 'Invalid admin template path.' );
		}

		if ( str_contains( $template, "\0" )
			|| str_contains( $template, '\\' )
			|| str_contains( $template, '..' )
			|| str_contains( $template, ':' )
			|| str_starts_with( $template, '/' )
		) {
			throw new \RuntimeException( 'Invalid admin template path.' );
		}

		if ( preg_match( '/^[a-z0-9_\/-]+\.php$/', $template ) !== 1 ) {
			throw new \RuntimeException( 'Invalid admin template path.' );
		}

		if ( !isset( self::ALLOWED_TEMPLATES[ $template ] ) ) {
			throw new \RuntimeException( 'Admin template is not registered.' );
		}
	}

	private function normalizePath( string $path ) :string {
		return rtrim( str_replace( '\\', '/', $path ), '/' );
	}
}
