<?php

declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Mandate\Tooling\CommandRunner;
use FernleafSystems\Wordpress\Plugin\Mandate\Tooling\RuntimePackageBuilder;
use FernleafSystems\Wordpress\Plugin\Mandate\Tooling\TemporaryDirectoryManager;
use FernleafSystems\Wordpress\Plugin\Mandate\Tooling\ZipBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

final class Wpm_Test_Package_CommandRunner extends CommandRunner {

	/**
	 * @param string[] $command
	 */
	public function run( array $command, ?string $workingDir = null ) :void {
		if ( !in_array( 'install', $command, true ) || $workingDir === null ) {
			return;
		}

		$filesystem = new Filesystem();
		$filesystem->mkdir( Path::join( $workingDir, 'vendor' ) );
		$filesystem->dumpFile( Path::join( $workingDir, 'vendor/autoload.php' ), "<?php\n" );
		$filesystem->dumpFile( Path::join( $workingDir, 'composer.lock' ), "{}\n" );
	}

	/**
	 * @return string[]
	 */
	public function getComposerCommand() :array {
		return [ 'composer' ];
	}
}

final class ToolingTest extends Wpm_Test_Case {

	public function testTemporaryDirectoryManagerCreatesDirectoryInsideSystemTemp() :void {
		$manager = new TemporaryDirectoryManager();
		$directory = $manager->create( 'mandate-test' );

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
		$directory = $manager->create( 'mandate-test' );
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

	public function testTemporaryDirectoryManagerRefusesEmptyNormalizedPrefix() :void {
		$manager = new TemporaryDirectoryManager();

		$this->assertThrowsRuntimeException(
			static function () use ( $manager ) :void {
				$manager->create( ' -._ ' );
			}
		);
	}

	public function testTemporaryDirectoryManagerRefusesTempFile() :void {
		$manager = new TemporaryDirectoryManager();
		$file = \tempnam( \sys_get_temp_dir(), 'mandate-file-test-' );
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

	public function testZipBuilderRefusesMissingSourceDirectory() :void {
		$filesystem = new Filesystem();
		$zipPath = Path::join( \sys_get_temp_dir(), 'mandate-missing-source-'.\bin2hex( \random_bytes( 4 ) ).'.zip' );

		$this->assertThrowsRuntimeException(
			static function () use ( $filesystem, $zipPath ) :void {
				( new ZipBuilder( $filesystem, static function ( string $message ) :void {} ) )
					->build( Path::join( \sys_get_temp_dir(), 'mandate-missing-source' ), $zipPath, RuntimePackageBuilder::PLUGIN_SLUG );
			}
		);
	}

	public function testRuntimePackageAndZipExcludeStaticSiteDirectory() :void {
		$filesystem = new Filesystem();
		$fixtureRoot = Path::join( \sys_get_temp_dir(), 'mandate-package-fixture-'.\bin2hex( \random_bytes( 4 ) ) );
		$packageRoot = Path::join( \sys_get_temp_dir(), 'mandate-package-target-'.\bin2hex( \random_bytes( 4 ) ) );
		$packageDir = Path::join( $packageRoot, RuntimePackageBuilder::PLUGIN_SLUG );
		$zipPath = Path::join( $packageRoot, 'mandate.zip' );

		try {
			$this->createPackageFixture( $fixtureRoot, $filesystem );

			$builder = new RuntimePackageBuilder(
				$fixtureRoot,
				new Wpm_Test_Package_CommandRunner( $fixtureRoot, static function ( string $message ) :void {} ),
				$filesystem,
				static function ( string $message ) :void {}
			);
			$builder->build( $packageDir, $packageRoot, false );

			$this->assertTrue( \is_file( Path::join( $packageDir, 'plugin.php' ) ) );
			$this->assertTrue( \is_file( Path::join( $packageDir, 'assets/dist/admin-page.js' ) ) );
			$this->assertFalse( \file_exists( Path::join( $packageDir, 'site' ) ) );
			$this->assertPackageComposerJsonContainsRuntimeConfigOnly( $packageDir );

			( new ZipBuilder( $filesystem, static function ( string $message ) :void {} ) )
				->build( $packageDir, $zipPath, RuntimePackageBuilder::PLUGIN_SLUG );

			$zip = new ZipArchive();
			$this->assertSame( true, $zip->open( $zipPath ) );
			try {
				$names = [];
				for ( $index = 0; $index < $zip->numFiles; $index++ ) {
					$name = $zip->getNameIndex( $index );
					if ( \is_string( $name ) ) {
						$names[] = $name;
					}
				}
			}
			finally {
				$zip->close();
			}

			$this->assertTrue( in_array( 'mandate/plugin.php', $names, true ) );
			$this->assertTrue( in_array( 'mandate/assets/dist/admin-page.js', $names, true ) );
			$this->assertFalse( in_array( 'mandate/site/index.html', $names, true ) );
		}
		finally {
			$filesystem->remove( [
				$fixtureRoot,
				$packageRoot,
			] );
		}
	}

	private function assertPackageComposerJsonContainsRuntimeConfigOnly( string $packageDir ) :void {
		$json = \file_get_contents( Path::join( $packageDir, 'composer.json' ) );
		if ( !\is_string( $json ) ) {
			throw new RuntimeException( 'Failed to read package composer.json.' );
		}

		$config = \json_decode( $json, true );
		$this->assertIsArray( $config );
		$this->assertSame( [ 'php' => '>=8.1' ], $config[ 'require' ] );
		$this->assertSame( [ 'psr-4' => [ 'Fixture\\' => 'src/' ] ], $config[ 'autoload' ] );
		$this->assertArrayNotHasKey( 'require-dev', $config );
		$this->assertArrayNotHasKey( 'autoload-dev', $config );
		$this->assertArrayNotHasKey( 'scripts', $config );
		$this->assertArrayNotHasKey( 'minimum-stability', $config );
	}

	private function createPackageFixture( string $fixtureRoot, Filesystem $filesystem ) :void {
		$filesystem->mkdir( [
			Path::join( $fixtureRoot, 'src' ),
			Path::join( $fixtureRoot, 'assets/dist' ),
			Path::join( $fixtureRoot, 'site/assets' ),
		] );

		foreach ( [
			'plugin.php',
			'init.php',
			'unsupported.php',
			'readme.txt',
			'LICENSE',
			'src/Plugin.php',
			'assets/dist/admin-page.css',
			'assets/dist/admin-page.js',
			'site/index.html',
			'site/assets/shield-icon.png',
		] as $file ) {
			$filesystem->dumpFile( Path::join( $fixtureRoot, $file ), "fixture\n" );
		}

		$composer = [
			'name'        => 'fernleafsystems/mandate-fixture',
			'description' => 'Fixture package',
			'type'        => 'wordpress-plugin',
			'license'     => 'GPL-2.0-or-later',
			'require'     => [
				'php' => '>=8.1',
			],
			'require-dev' => [
				'phpunit/phpunit' => '^11.5',
			],
			'config'      => [],
			'autoload'    => [
				'psr-4' => [
					'Fixture\\' => 'src/',
				],
			],
			'autoload-dev' => [
				'psr-4' => [
					'Fixture\\Tests\\' => 'tests/',
				],
			],
			'scripts' => [
				'test' => 'phpunit',
			],
			'minimum-stability' => 'dev',
		];

		$json = \json_encode( $composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( !\is_string( $json ) ) {
			throw new RuntimeException( 'Failed to encode fixture composer config.' );
		}

		$filesystem->dumpFile( Path::join( $fixtureRoot, 'composer.json' ), $json.PHP_EOL );
	}
}
