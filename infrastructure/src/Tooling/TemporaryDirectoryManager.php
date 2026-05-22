<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Tooling;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class TemporaryDirectoryManager {

	private Filesystem $filesystem;

	public function __construct( ?Filesystem $filesystem = null ) {
		$this->filesystem = $filesystem ?? new Filesystem();
	}

	public function create( string $prefix ) :string {
		$prefix = $this->normalizePrefix( $prefix );
		$tempRoot = $this->systemTempRoot();

		for ( $attempt = 0; $attempt < 10; $attempt++ ) {
			$path = Path::join( $tempRoot, $prefix.\bin2hex( \random_bytes( 8 ) ) );
			if ( \file_exists( $path ) ) {
				continue;
			}

			$this->filesystem->mkdir( $path );
			$realPath = \realpath( $path );
			if ( $realPath === false ) {
				throw new \RuntimeException( 'Failed to resolve temporary directory: '.$path );
			}

			return Path::normalize( $realPath );
		}

		throw new \RuntimeException( 'Failed to create a unique temporary directory.' );
	}

	public function remove( string $directory ) :void {
		$realDirectory = \realpath( $directory );
		if ( $realDirectory === false ) {
			return;
		}

		$normalizedDirectory = Path::normalize( $realDirectory );
		$tempRoot = $this->systemTempRoot();

		if ( !\is_dir( $normalizedDirectory ) ) {
			throw new \RuntimeException( 'Refusing to remove non-directory temp path: '.$normalizedDirectory );
		}

		if ( $normalizedDirectory === $tempRoot || !Path::isBasePath( $tempRoot, $normalizedDirectory ) ) {
			throw new \RuntimeException( 'Refusing to remove directory outside system temp: '.$normalizedDirectory );
		}

		$this->filesystem->remove( $normalizedDirectory );
	}

	private function normalizePrefix( string $prefix ) :string {
		$prefix = \preg_replace( '/[^a-zA-Z0-9._-]+/', '-', $prefix ) ?? '';
		$prefix = \trim( $prefix, '.-_' );
		if ( $prefix === '' ) {
			throw new \RuntimeException( 'Temporary directory prefix cannot be empty.' );
		}

		return $prefix.'-';
	}

	private function systemTempRoot() :string {
		$tempRoot = \sys_get_temp_dir();
		if ( $tempRoot === '' ) {
			throw new \RuntimeException( 'Unable to resolve system temp directory.' );
		}

		$realTempRoot = \realpath( $tempRoot );
		if ( $realTempRoot === false ) {
			throw new \RuntimeException( 'Unable to resolve system temp directory: '.$tempRoot );
		}

		return Path::normalize( $realTempRoot );
	}
}
