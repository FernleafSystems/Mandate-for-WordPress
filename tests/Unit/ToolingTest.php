<?php

declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Tooling\TemporaryDirectoryManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

final class ToolingTest extends Aps_Test_Case {

	public function testTemporaryDirectoryManagerCreatesDirectoryInsideSystemTemp() :void {
		$manager = new TemporaryDirectoryManager();
		$directory = $manager->create( 'application-password-scoper-test' );

		try {
			$tempRoot = Path::normalize( \realpath( \sys_get_temp_dir() ) ?: \sys_get_temp_dir() );

			$this->assertTrue( \is_dir( $directory ) );
			$this->assertTrue( Path::isBasePath( $tempRoot, $directory ) );
		}
		finally {
			$manager->remove( $directory );
		}
	}

	public function testTemporaryDirectoryManagerRemovesTempDirectory() :void {
		$manager = new TemporaryDirectoryManager();
		$directory = $manager->create( 'application-password-scoper-test' );
		\file_put_contents( Path::join( $directory, 'fixture.txt' ), 'fixture' );

		$manager->remove( $directory );

		$this->assertFalse( \file_exists( $directory ) );
	}

	public function testTemporaryDirectoryManagerRefusesSystemTempRoot() :void {
		$manager = new TemporaryDirectoryManager();

		$this->assertThrowsRuntimeException(
			static function () use ( $manager ) :void {
				$manager->remove( \sys_get_temp_dir() );
			}
		);
	}

	public function testTemporaryDirectoryManagerRefusesTempFile() :void {
		$manager = new TemporaryDirectoryManager();
		$file = \tempnam( \sys_get_temp_dir(), 'application-password-scoper-file-test-' );
		if ( !\is_string( $file ) ) {
			throw new RuntimeException( 'Failed to create temp file fixture.' );
		}

		try {
			$this->assertThrowsRuntimeException(
				static function () use ( $manager, $file ) :void {
					$manager->remove( $file );
				}
			);
			$this->assertTrue( \is_file( $file ) );
		}
		finally {
			if ( \is_file( $file ) ) {
				\unlink( $file );
			}
		}
	}

	public function testTemporaryDirectoryManagerRefusesDirectoryOutsideSystemTemp() :void {
		$manager = new TemporaryDirectoryManager();
		$filesystem = new Filesystem();
		$directory = Path::join(
			\dirname( __DIR__, 2 ),
			'build',
			'temporary-directory-manager-refusal-test-'.\bin2hex( \random_bytes( 4 ) )
		);
		$filesystem->mkdir( $directory );

		try {
			$this->assertThrowsRuntimeException(
				static function () use ( $manager, $directory ) :void {
					$manager->remove( $directory );
				}
			);
			$this->assertTrue( \is_dir( $directory ) );
		}
		finally {
			$filesystem->remove( $directory );
		}
	}
}
