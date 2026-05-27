<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Admin;

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

	private const RESERVED_DATA_KEYS = [
		'GLOBALS' => true,
		'_COOKIE' => true,
		'_ENV' => true,
		'_FILES' => true,
		'_GET' => true,
		'_POST' => true,
		'_REQUEST' => true,
		'_SERVER' => true,
		'_SESSION' => true,
		'this' => true,
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
		$this->assertValidDataKeys( $data );

		ob_start();
		try {
			extract( $data, EXTR_SKIP );
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
			'data-wpm-admin-lock-input'      => true,
			'data-wpm-admin-lock-status'     => true,
			'data-wpm-capability-action'     => true,
			'data-wpm-capability-area'       => true,
			'data-wpm-capability-groups'     => true,
			'data-wpm-capability-grouping'   => true,
			'data-wpm-capability-grouping-config' => true,
			'data-wpm-capability-grouping-mode'   => true,
			'data-wpm-capability-index-link' => true,
			'data-wpm-capability-item'       => true,
			'data-wpm-capability-key'        => true,
			'data-wpm-capability-mode'       => true,
			'data-wpm-capability-name'       => true,
			'data-wpm-capability-panel'      => true,
			'data-wpm-capability-section-index'  => true,
			'data-wpm-capability-section'    => true,
			'data-wpm-capability-section-target' => true,
			'data-wpm-capability-source'     => true,
			'data-wpm-capability-source-panel' => true,
			'data-wpm-capability-source-tab' => true,
			'data-wpm-capability-source-tabs' => true,
			'data-wpm-capability-type'       => true,
			'data-wpm-expiration-input'      => true,
			'data-wpm-expiration-state'      => true,
			'data-wpm-expiration-summary'    => true,
			'data-wpm-role-snapshot-status'  => true,
			'data-wpm-select-panel'          => true,
			'data-wpm-select-section'        => true,
			'data-wpm-select-state'          => true,
			'data-wpm-selection-form'        => true,
			'data-wpm-selection-status'      => true,
			'data-wpm-tooltip'               => true,
			'data-wpm-tooltip-text'          => true,
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

	/**
	 * @param array<string,mixed> $data
	 */
	private function assertValidDataKeys( array $data ) :void {
		foreach ( array_keys( $data ) as $key ) {
			if ( !is_string( $key )
				|| preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $key ) !== 1
				|| isset( self::RESERVED_DATA_KEYS[ $key ] )
			) {
				throw new \RuntimeException( 'Invalid admin template data key.' );
			}
		}
	}

	private function normalizePath( string $path ) :string {
		return rtrim( str_replace( '\\', '/', $path ), '/' );
	}
}
