<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Admin;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class AdminTemplateRenderer {

	private string $baseDirectory;

	public function __construct( ?string $baseDirectory = null ) {
		$this->baseDirectory = rtrim( $baseDirectory ?? __DIR__.'/templates', "\\/" );
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function render( string $template, array $data ) :string {
		$templatePath = $this->resolveTemplatePath( $template );

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
		$relative = str_replace( '\\', '/', trim( $template ) );
		$relative = ltrim( $relative, '/' );
		if ( $relative === '' || str_contains( $relative, "\0" ) || str_contains( $relative, '..' ) ) {
			throw new \RuntimeException( 'Invalid admin template path.' );
		}

		$base = realpath( $this->baseDirectory );
		if ( $base === false ) {
			throw new \RuntimeException( 'Admin template directory is missing.' );
		}

		$path = realpath( $base.DIRECTORY_SEPARATOR.str_replace( '/', DIRECTORY_SEPARATOR, $relative ) );
		if ( $path === false || !is_file( $path ) ) {
			throw new \RuntimeException( 'Admin template is missing.' );
		}

		$base = str_replace( '\\', '/', $base );
		$path = str_replace( '\\', '/', $path );
		if ( $path !== $base && !str_starts_with( $path, $base.'/' ) ) {
			throw new \RuntimeException( 'Admin template path escapes the template directory.' );
		}

		return $path;
	}
}
