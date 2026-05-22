<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Tooling;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class ZipBuilder {

	private Filesystem $filesystem;

	/** @var callable(string):void */
	private $logger;

	/**
	 * @param callable(string):void $logger
	 */
	public function __construct( Filesystem $filesystem, callable $logger ) {
		$this->filesystem = $filesystem;
		$this->logger = $logger;
	}

	public function build( string $sourceDir, string $outputZip, string $rootFolder ) :int {
		$sourceDir = Path::normalize( $sourceDir );
		$outputZip = Path::normalize( $outputZip );

		if ( !\is_dir( $sourceDir ) ) {
			throw new \RuntimeException( 'Cannot zip missing source directory: '.$sourceDir );
		}

		$this->filesystem->mkdir( \dirname( $outputZip ) );

		$zip = new \ZipArchive();
		$result = $zip->open( $outputZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		if ( $result !== true ) {
			throw new \RuntimeException( 'Failed to create zip file: '.$outputZip.' (error code: '.$result.')' );
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $sourceDir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		$fileCount = 0;
		foreach ( $iterator as $item ) {
			/** @var \SplFileInfo $item */
			if ( !$item->isFile() ) {
				continue;
			}

			$path = $item->getPathname();
			$relativePath = Path::makeRelative( $path, $sourceDir );
			$zipPath = Path::join( $rootFolder, \str_replace( '\\', '/', $relativePath ) );

			if ( !$zip->addFile( $path, $zipPath ) ) {
				$zip->close();
				throw new \RuntimeException( 'Failed to add file to zip: '.$relativePath );
			}

			$fileCount++;
		}

		if ( !$zip->close() ) {
			throw new \RuntimeException( 'Failed to finalize zip file: '.$outputZip );
		}

		$this->log( 'Zip created at: '.$outputZip );
		$this->log( 'Files included: '.(string)$fileCount );

		return $fileCount;
	}

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}
}
