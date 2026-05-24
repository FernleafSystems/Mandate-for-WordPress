<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Tooling;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class RuntimePackageBuilder {

	public const PLUGIN_SLUG = 'mandate';
	public const VARIANT_WORDPRESS_ORG = 'wordpress-org';
	public const VARIANT_GITHUB = 'github';

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

	private const GITHUB_UPDATER_TEMPLATE = 'infrastructure/templates/github-updater.php';
	private const GITHUB_UPDATE_URI = 'https://github.com/FernleafSystems/Mandate-for-WordPress';
	private const GITHUB_UPDATER_DEPENDENCY = 'yahnis-elsts/plugin-update-checker';
	private const GITHUB_UPDATER_VERSION = '^5.6';

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

	public function build(
		string $targetDir,
		string $allowedCleanupRoot,
		bool $buildAssets = true,
		string $variant = self::VARIANT_WORDPRESS_ORG
	) :string {
		$targetDir = Path::normalize( $targetDir );
		$allowedCleanupRoot = Path::normalize( $allowedCleanupRoot );
		$variant = $this->normalizeVariant( $variant );

		if ( $buildAssets ) {
			$this->runAssetBuild();
		}

		$this->assertRuntimeSourcesExist();
		$this->prepareTargetDirectory( $targetDir, $allowedCleanupRoot );
		$this->copyRuntimeFiles( $targetDir );
		$this->applyVariantTransforms( $targetDir, $variant );
		$this->writePackageComposerJson( $targetDir, $variant );
		$this->installPackageComposerDependencies( $targetDir );
		$this->removePackageComposerLock( $targetDir );

		$this->assertPackageIsUsable( $targetDir, $variant );
		$this->log( 'Runtime package created at: '.$targetDir );

		return $targetDir;
	}

	private function normalizeVariant( string $variant ) :string {
		$variant = \trim( $variant );
		if ( \in_array( $variant, [ self::VARIANT_WORDPRESS_ORG, self::VARIANT_GITHUB ], true ) ) {
			return $variant;
		}

		throw new \RuntimeException(
			\sprintf(
				'Unknown package variant "%s". Expected one of: %s.',
				$variant,
				\implode( ', ', [ self::VARIANT_WORDPRESS_ORG, self::VARIANT_GITHUB ] )
			)
		);
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

	private function applyVariantTransforms( string $targetDir, string $variant ) :void {
		if ( $variant !== self::VARIANT_GITHUB ) {
			return;
		}

		$this->copyGithubUpdater( $targetDir );
		$this->addGithubUpdateUri( $targetDir );
		$this->addGithubUpdaterBootstrap( $targetDir );
	}

	private function copyGithubUpdater( string $targetDir ) :void {
		$templatePath = Path::join( $this->projectRoot, self::GITHUB_UPDATER_TEMPLATE );
		if ( !\is_file( $templatePath ) ) {
			throw new \RuntimeException( 'Missing GitHub updater template: '.self::GITHUB_UPDATER_TEMPLATE );
		}

		$this->filesystem->copy( $templatePath, Path::join( $targetDir, 'github-updater.php' ), true );
	}

	private function addGithubUpdateUri( string $targetDir ) :void {
		$pluginPath = Path::join( $targetDir, 'plugin.php' );
		$content = $this->readPackageFile( $pluginPath );

		if ( \preg_match( '/^\s*\*\s*Update URI:/mi', $content ) === 1 ) {
			throw new \RuntimeException( 'Packaged plugin.php already contains an Update URI header.' );
		}

		$updated = \preg_replace(
			'/^(\s*\*\s*Plugin URI:\s*.+)$/m',
			'$1'."\n".' * Update URI: '.self::GITHUB_UPDATE_URI,
			$content,
			1
		);
		if ( !\is_string( $updated ) || $updated === $content ) {
			throw new \RuntimeException( 'Failed to add GitHub Update URI header to packaged plugin.php.' );
		}

		$this->filesystem->dumpFile( $pluginPath, $updated );
	}

	private function addGithubUpdaterBootstrap( string $targetDir ) :void {
		$initPath = Path::join( $targetDir, 'init.php' );
		$content = $this->readPackageFile( $initPath );
		$needle = "\t\trequire_once \$mandate_autoload;\n";
		$replacement = $needle."\t\trequire_once __DIR__.'/github-updater.php';\n";

		if ( \str_contains( $content, "github-updater.php" ) ) {
			throw new \RuntimeException( 'Packaged init.php already contains the GitHub updater bootstrap.' );
		}

		$updated = \str_replace( $needle, $replacement, $content, $count );
		if ( $count !== 1 ) {
			throw new \RuntimeException( 'Failed to add GitHub updater bootstrap to packaged init.php.' );
		}

		$this->filesystem->dumpFile( $initPath, $updated );
	}

	private function readPackageFile( string $path ) :string {
		$content = \file_get_contents( $path );
		if ( $content === false ) {
			throw new \RuntimeException( 'Failed to read package file: '.$path );
		}

		return $content;
	}

	private function writePackageComposerJson( string $targetDir, string $variant ) :void {
		$composer = $this->buildPackageComposerConfig( $variant );

		$json = \json_encode( $composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( !\is_string( $json ) ) {
			throw new \RuntimeException( 'Failed to encode package composer.json.' );
		}

		$this->filesystem->dumpFile( Path::join( $targetDir, 'composer.json' ), $json.PHP_EOL );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildPackageComposerConfig( string $variant ) :array {
		$sourceConfig = $this->readSourceComposerConfig();
		$config = $this->requireArray( $sourceConfig, 'config' );
		$config[ 'allow-plugins' ] = new \stdClass();
		$require = $this->requireArray( $sourceConfig, 'require' );
		if ( $variant === self::VARIANT_GITHUB ) {
			$require[ self::GITHUB_UPDATER_DEPENDENCY ] = self::GITHUB_UPDATER_VERSION;
			\ksort( $require );
		}

		return [
			'name'        => $this->requireString( $sourceConfig, 'name' ),
			'description' => $this->requireString( $sourceConfig, 'description' ),
			'type'        => $this->requireString( $sourceConfig, 'type' ),
			'license'     => $this->requireString( $sourceConfig, 'license' ),
			'require'     => $require,
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

	private function assertPackageIsUsable( string $targetDir, string $variant ) :void {
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

		if ( $variant === self::VARIANT_GITHUB && !\is_file( Path::join( $targetDir, 'github-updater.php' ) ) ) {
			throw new \RuntimeException( 'Package verification failed; missing GitHub updater bootstrap.' );
		}
	}

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}
}
