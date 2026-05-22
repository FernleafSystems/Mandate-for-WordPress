<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Tooling;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class RuntimePackageBuilder {

	public const PLUGIN_SLUG = 'mandate';

	private const RUNTIME_FILES = [
		'plugin.php',
		'init.php',
		'unsupported.php',
		'readme.txt',
		'LICENSE',
	];

	private const RUNTIME_DIRECTORIES = [
		'src',
		'assets/dist',
	];

	private const BUILT_ASSET_FILES = [
		'assets/dist/admin-page.css',
		'assets/dist/admin-page.js',
	];

	private string $projectRoot;

	private CommandRunner $commandRunner;

	private Filesystem $filesystem;

	/** @var callable(string):void */
	private $logger;

	/**
	 * @param callable(string):void $logger
	 */
	public function __construct(
		string $projectRoot,
		CommandRunner $commandRunner,
		Filesystem $filesystem,
		callable $logger
	) {
		$this->projectRoot = Path::normalize( $projectRoot );
		$this->commandRunner = $commandRunner;
		$this->filesystem = $filesystem;
		$this->logger = $logger;
	}

	public function build( string $targetDir, string $allowedCleanupRoot, bool $buildAssets = true ) :string {
		$targetDir = Path::normalize( $targetDir );
		$allowedCleanupRoot = Path::normalize( $allowedCleanupRoot );

		if ( $buildAssets ) {
			$this->runAssetBuild();
		}

		$this->assertRuntimeSourcesExist();
		$this->prepareTargetDirectory( $targetDir, $allowedCleanupRoot );
		$this->copyRuntimeFiles( $targetDir );
		$this->writePackageComposerJson( $targetDir );
		$this->installPackageComposerDependencies( $targetDir );
		$this->removePackageComposerLock( $targetDir );

		$this->assertPackageIsUsable( $targetDir );
		$this->log( 'Runtime package created at: '.$targetDir );

		return $targetDir;
	}

	private function runAssetBuild() :void {
		$this->commandRunner->run(
			[ 'npm', 'ci', '--no-audit', '--no-fund' ],
			$this->projectRoot
		);
		$this->commandRunner->run(
			[ 'npm', 'run', 'build' ],
			$this->projectRoot
		);
	}

	private function assertRuntimeSourcesExist() :void {
		foreach ( self::RUNTIME_FILES as $file ) {
			$path = Path::join( $this->projectRoot, $file );
			if ( !\is_file( $path ) ) {
				throw new \RuntimeException( 'Missing runtime file: '.$file );
			}
		}

		foreach ( self::RUNTIME_DIRECTORIES as $directory ) {
			$path = Path::join( $this->projectRoot, $directory );
			if ( !\is_dir( $path ) ) {
				throw new \RuntimeException( 'Missing runtime directory: '.$directory );
			}
		}

		foreach ( self::BUILT_ASSET_FILES as $asset ) {
			$path = Path::join( $this->projectRoot, $asset );
			if ( !\is_file( $path ) ) {
				throw new \RuntimeException( 'Missing built admin asset: '.$asset.'. Run npm run build.' );
			}
		}
	}

	private function prepareTargetDirectory( string $targetDir, string $allowedCleanupRoot ) :void {
		$this->filesystem->mkdir( $allowedCleanupRoot );
		$this->removeDirectoryInside( $targetDir, $allowedCleanupRoot );
		$this->filesystem->mkdir( $targetDir );
	}

	private function removeDirectoryInside( string $directory, string $allowedRoot ) :void {
		if ( !\file_exists( $directory ) ) {
			return;
		}

		$realDirectory = \realpath( $directory );
		$realAllowedRoot = \realpath( $allowedRoot );
		if ( $realDirectory === false || $realAllowedRoot === false ) {
			throw new \RuntimeException( 'Could not resolve package cleanup path.' );
		}

		$normalizedDirectory = Path::normalize( $realDirectory );
		$normalizedAllowedRoot = Path::normalize( $realAllowedRoot );

		if ( $normalizedDirectory === $normalizedAllowedRoot || !Path::isBasePath( $normalizedAllowedRoot, $normalizedDirectory ) ) {
			throw new \RuntimeException(
				\sprintf(
					'Refusing to remove directory outside allowed package root. Directory: %s Allowed root: %s',
					$normalizedDirectory,
					$normalizedAllowedRoot
				)
			);
		}

		$this->filesystem->remove( $normalizedDirectory );
	}

	private function copyRuntimeFiles( string $targetDir ) :void {
		$this->log( 'Copying runtime package files...' );

		foreach ( self::RUNTIME_FILES as $file ) {
			$this->filesystem->copy(
				Path::join( $this->projectRoot, $file ),
				Path::join( $targetDir, $file ),
				true
			);
		}

		foreach ( self::RUNTIME_DIRECTORIES as $directory ) {
			$this->filesystem->mirror(
				Path::join( $this->projectRoot, $directory ),
				Path::join( $targetDir, $directory )
			);
		}
	}

	private function writePackageComposerJson( string $targetDir ) :void {
		$composer = $this->buildPackageComposerConfig();

		$json = \json_encode( $composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( !\is_string( $json ) ) {
			throw new \RuntimeException( 'Failed to encode package composer.json.' );
		}

		$this->filesystem->dumpFile( Path::join( $targetDir, 'composer.json' ), $json.PHP_EOL );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildPackageComposerConfig() :array {
		$sourceConfig = $this->readSourceComposerConfig();
		$config = $this->requireArray( $sourceConfig, 'config' );
		$config[ 'allow-plugins' ] = new \stdClass();

		return [
			'name'        => $this->requireString( $sourceConfig, 'name' ),
			'description' => $this->requireString( $sourceConfig, 'description' ),
			'type'        => $this->requireString( $sourceConfig, 'type' ),
			'license'     => $this->requireString( $sourceConfig, 'license' ),
			'require'     => $this->requireArray( $sourceConfig, 'require' ),
			'config'      => $config,
			'autoload'    => $this->requireArray( $sourceConfig, 'autoload' ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function readSourceComposerConfig() :array {
		$path = Path::join( $this->projectRoot, 'composer.json' );
		$content = \file_get_contents( $path );
		if ( $content === false ) {
			throw new \RuntimeException( 'Failed to read source composer.json.' );
		}

		$config = \json_decode( $content, true );
		if ( !\is_array( $config ) ) {
			throw new \RuntimeException( 'Source composer.json is not valid JSON: '.\json_last_error_msg() );
		}

		return $config;
	}

	/**
	 * @param array<string, mixed> $config
	 */
	private function requireString( array $config, string $key ) :string {
		$value = $config[ $key ] ?? null;
		if ( !\is_string( $value ) || $value === '' ) {
			throw new \RuntimeException( 'Source composer.json must define a non-empty string for '.$key.'.' );
		}

		return $value;
	}

	/**
	 * @param array<string, mixed> $config
	 * @return array<string, mixed>
	 */
	private function requireArray( array $config, string $key ) :array {
		$value = $config[ $key ] ?? null;
		if ( !\is_array( $value ) ) {
			throw new \RuntimeException( 'Source composer.json must define an object for '.$key.'.' );
		}

		return $value;
	}

	private function installPackageComposerDependencies( string $targetDir ) :void {
		$this->commandRunner->run(
			\array_merge(
				$this->commandRunner->getComposerCommand(),
				[
					'install',
					'--no-dev',
					'--no-interaction',
					'--prefer-dist',
					'--optimize-autoloader',
				]
			),
			$targetDir
		);
	}

	private function removePackageComposerLock( string $targetDir ) :void {
		$this->filesystem->remove( [
			Path::join( $targetDir, 'composer.lock' ),
		] );
	}

	private function assertPackageIsUsable( string $targetDir ) :void {
		foreach ( [
			...self::RUNTIME_FILES,
			'composer.json',
			'vendor/autoload.php',
			...self::BUILT_ASSET_FILES,
		] as $file ) {
			$path = Path::join( $targetDir, $file );
			if ( !\is_file( $path ) ) {
				throw new \RuntimeException( 'Package verification failed; missing file: '.$file );
			}
		}

		foreach ( self::RUNTIME_DIRECTORIES as $directory ) {
			$path = Path::join( $targetDir, $directory );
			if ( !\is_dir( $path ) ) {
				throw new \RuntimeException( 'Package verification failed; missing directory: '.$directory );
			}
		}
	}

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}
}
