<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Admin;

class AdminTemplateRenderer {

	private const ALLOWED_TEMPLATES = [
		'admin-page.php' => true,
		'partials/capability-item.php' => true,
		'partials/capability-panel.php' => true,
		'partials/capability-section.php' => true,
		'partials/notice.php' => true,
		'partials/password-detail.php' => true,
		'partials/password-summary.php' => true,
		'partials/role-summary.php' => true,
		'partials/scope-form.php' => true,
		'partials/selection-form.php' => true,
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
