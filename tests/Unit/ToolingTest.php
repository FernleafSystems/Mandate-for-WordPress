<?php

declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Mandate\Tooling\CommandRunner;
use FernleafSystems\Wordpress\Plugin\Mandate\PluginIdentity;
use FernleafSystems\Wordpress\Plugin\Mandate\Tooling\RuntimePackageBuilder;
use FernleafSystems\Wordpress\Plugin\Mandate\Tooling\TemporaryDirectoryManager;
use FernleafSystems\Wordpress\Plugin\Mandate\Tooling\ZipBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

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

		$composerJson = \file_get_contents( Path::join( $workingDir, 'composer.json' ) );
		$composer = \is_string( $composerJson ) ? \json_decode( $composerJson, true ) : null;
		if ( \is_array( $composer )
			&& \array_key_exists( 'yahnis-elsts/plugin-update-checker', $composer[ 'require' ] ?? [] )
		) {
			$filesystem->mkdir( Path::join( $workingDir, 'vendor/yahnis-elsts/plugin-update-checker' ) );
			$filesystem->dumpFile(
				Path::join( $workingDir, 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php' ),
				"<?php\n"
			);
		}
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

			$this->assertTrue( \is_file( Path::join( $packageDir, RuntimePackageBuilder::MAIN_PLUGIN_FILE ) ) );
			$this->assertTrue( \is_file( Path::join( $packageDir, 'assets/dist/admin-page.js' ) ) );
			$this->assertTrue( \is_file( Path::join( $packageDir, 'assets/dist/info-square.svg' ) ) );
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

			$this->assertTrue( in_array( PluginIdentity::PACKAGE_ROOT.PluginIdentity::MAIN_FILE, $names, true ) );
			$this->assertTrue( in_array( PluginIdentity::PACKAGE_ROOT.'assets/dist/admin-page.js', $names, true ) );
			$this->assertTrue( in_array( PluginIdentity::PACKAGE_ROOT.'assets/dist/info-square.svg', $names, true ) );
			$this->assertFalse( in_array( PluginIdentity::PACKAGE_ROOT.'site/index.html', $names, true ) );
		}
		finally {
			$filesystem->remove( [
				$fixtureRoot,
				$packageRoot,
			] );
		}
	}

	public function testBuiltAdminCssReferencesPackagedInfoIconAsset() :void {
		$css = $this->readProjectFile( 'assets/dist/admin-page.css' );

		$this->assertStringContainsString( 'url(./info-square.svg)', $css );
		$this->assertStringNotContainsString( 'data:image/svg', $css );
		$this->assertStringNotContainsString( 'url(/info-square.svg)', $css );
		$this->assertStringNotContainsString( '?no-inline', $css );
	}

	public function testWordPressOrgPackageDoesNotContainGithubUpdaterLeakage() :void {
		$filesystem = new Filesystem();
		$projectRoot = \dirname( __DIR__, 2 );
		$packageRoot = Path::join( \sys_get_temp_dir(), 'mandate-wordpress-org-package-'.\bin2hex( \random_bytes( 4 ) ) );
		$packageDir = Path::join( $packageRoot, RuntimePackageBuilder::PLUGIN_SLUG );

		try {
			$this->buildPackage( $projectRoot, $packageDir, $packageRoot, RuntimePackageBuilder::VARIANT_WORDPRESS_ORG, $filesystem );

			$this->assertFalse( \file_exists( Path::join( $packageDir, 'github-updater.php' ) ) );
			$this->assertPackageComposerJsonDoesNotRequireGithubUpdater( $packageDir );
			$this->assertStringNotContainsString( 'Update URI:', $this->readPackageFile( $packageDir, RuntimePackageBuilder::MAIN_PLUGIN_FILE ) );
			$this->assertStringNotContainsString( 'github-updater.php', $this->readPackageFile( $packageDir, 'init.php' ) );
			$this->assertPackageDoesNotContainGithubUpdaterTokens( $packageDir );
		}
		finally {
			$filesystem->remove( $packageRoot );
		}
	}

	public function testGithubPackageInjectsUpdaterOnlyForGithubVariant() :void {
		$filesystem = new Filesystem();
		$projectRoot = \dirname( __DIR__, 2 );
		$packageRoot = Path::join( \sys_get_temp_dir(), PluginIdentity::GITHUB_ASSET_PREFIX.'-package-'.\bin2hex( \random_bytes( 4 ) ) );
		$packageDir = Path::join( $packageRoot, RuntimePackageBuilder::PLUGIN_SLUG );

		try {
			$this->buildPackage( $projectRoot, $packageDir, $packageRoot, RuntimePackageBuilder::VARIANT_GITHUB, $filesystem );

			$this->assertTrue( \is_file( Path::join( $packageDir, 'github-updater.php' ) ) );
			$this->assertPackageComposerJsonRequiresGithubUpdater( $packageDir );
			$this->assertStringContainsString(
				'Update URI: https://github.com/FernleafSystems/Mandate-for-WordPress',
				$this->readPackageFile( $packageDir, RuntimePackageBuilder::MAIN_PLUGIN_FILE )
			);
			$this->assertStringContainsString( "require_once __DIR__.'/github-updater.php';", $this->readPackageFile( $packageDir, 'init.php' ) );

			$updater = $this->readPackageFile( $packageDir, 'github-updater.php' );
			$this->assertStringContainsString( 'https://github.com/FernleafSystems/Mandate-for-WordPress/', $updater );
			$this->assertStringContainsString( 'PluginIdentity::GITHUB_ASSET_PREFIXES', $updater );
			$this->assertStringContainsString( "YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory", $updater );
		}
		finally {
			$filesystem->remove( $packageRoot );
		}
	}

	public function testGithubUpdaterReleaseAssetMatcherKeepsCurrentAndLegacyPrefixes() :void {
		$githubUpdater = $this->readProjectFile( 'infrastructure/templates/github-updater.php' );

		$this->assertSame(
			[
				PluginIdentity::GITHUB_ASSET_PREFIX,
				PluginIdentity::LEGACY_GITHUB_ASSET_PREFIX,
			],
			PluginIdentity::GITHUB_ASSET_PREFIXES
		);
		$this->assertStringContainsString( 'PluginIdentity::GITHUB_ASSET_PREFIXES', $githubUpdater );
		$this->assertStringContainsString( 'preg_quote', $githubUpdater );
		$this->assertStringContainsString( 'enableReleaseAssets', $githubUpdater );
	}

	public function testPackageVerifierAcceptsAllReleasePackageContracts() :void {
		$filesystem = new Filesystem();
		$projectRoot = \dirname( __DIR__, 2 );
		$packageRoot = Path::join( \sys_get_temp_dir(), 'mandate-verifier-packages-'.\bin2hex( \random_bytes( 4 ) ) );

		try {
			$wordpressOrgZip = $this->buildProjectPackageZip(
				$projectRoot,
				$packageRoot,
				RuntimePackageBuilder::VARIANT_WORDPRESS_ORG,
				PluginIdentity::SLUG.'-test.zip',
				$filesystem
			);
			$currentGithubZip = $this->buildProjectPackageZip(
				$projectRoot,
				$packageRoot,
				RuntimePackageBuilder::VARIANT_GITHUB,
				PluginIdentity::GITHUB_ASSET_PREFIX.'-test.zip',
				$filesystem
			);
			$legacyGithubZip = $this->buildProjectPackageZip(
				$projectRoot,
				$packageRoot,
				RuntimePackageBuilder::VARIANT_GITHUB,
				PluginIdentity::LEGACY_GITHUB_ASSET_PREFIX.'-test.zip',
				$filesystem
			);

			$this->assertPackageZipHasEntry( $wordpressOrgZip, PluginIdentity::PACKAGE_ROOT.'vendor/autoload.php' );
			$this->assertPackageZipHasEntry( $currentGithubZip, PluginIdentity::PACKAGE_ROOT.'vendor/autoload.php' );
			$this->assertPackageZipHasEntry( $legacyGithubZip, PluginIdentity::PACKAGE_ROOT.'vendor/autoload.php' );
			$this->assertPackageZipHasEntry( $currentGithubZip, PluginIdentity::PACKAGE_ROOT.'github-updater.php' );
			$this->assertPackageZipHasEntry( $legacyGithubZip, PluginIdentity::PACKAGE_ROOT.'github-updater.php' );
			$this->assertPackageZipHasEntry(
				$currentGithubZip,
				PluginIdentity::PACKAGE_ROOT.'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php'
			);
			$this->assertPackageZipHasEntry(
				$legacyGithubZip,
				PluginIdentity::PACKAGE_ROOT.'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php'
			);

			$this->assertPackageVerifierPasses( RuntimePackageBuilder::VARIANT_WORDPRESS_ORG, $wordpressOrgZip );
			$this->assertPackageVerifierPasses( RuntimePackageBuilder::VARIANT_GITHUB, $currentGithubZip );
			$this->assertPackageVerifierPasses( RuntimePackageBuilder::VARIANT_GITHUB, $legacyGithubZip );
		}
		finally {
			$filesystem->remove( $packageRoot );
		}
	}

	public function testPackageVerifierRejectsSourceArchiveWithoutComposerAutoload() :void {
		$filesystem = new Filesystem();
		$packageRoot = Path::join( \sys_get_temp_dir(), 'mandate-source-archive-'.\bin2hex( \random_bytes( 4 ) ) );
		$zipPath = Path::join( $packageRoot, 'source-archive.zip' );

		try {
			$filesystem->mkdir( $packageRoot );
			$zip = new ZipArchive();
			$this->assertSame( true, $zip->open( $zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) );
			try {
				$zip->addFromString( PluginIdentity::PACKAGE_ROOT.PluginIdentity::MAIN_FILE, "<?php\n" );
				$zip->addFromString( PluginIdentity::PACKAGE_ROOT.'init.php', "<?php\n" );
				$zip->addFromString( PluginIdentity::PACKAGE_ROOT.'composer.json', "{\"require\":{\"php\":\">=8.2\"}}\n" );
				$zip->addFromString(
					PluginIdentity::PACKAGE_ROOT.'src/PluginIdentity.php',
					$this->readProjectFile( 'src/PluginIdentity.php' )
				);
			}
			finally {
				$zip->close();
			}

			$result = $this->runPackageVerifier( RuntimePackageBuilder::VARIANT_WORDPRESS_ORG, $zipPath );

			$this->assertNotSame( 0, $result[ 'exit_code' ] );
			$this->assertStringContainsString( 'vendor/autoload.php', $result[ 'output' ] );
		}
		finally {
			$filesystem->remove( $packageRoot );
		}
	}

	public function testRuntimePackageBuilderRejectsUnknownPackageVariant() :void {
		$filesystem = new Filesystem();
		$projectRoot = \dirname( __DIR__, 2 );
		$packageRoot = Path::join( \sys_get_temp_dir(), 'mandate-unknown-variant-'.\bin2hex( \random_bytes( 4 ) ) );
		$packageDir = Path::join( $packageRoot, RuntimePackageBuilder::PLUGIN_SLUG );

		try {
			$this->assertThrowsRuntimeException(
				function () use ( $projectRoot, $packageDir, $packageRoot, $filesystem ) :void {
					$this->buildPackage( $projectRoot, $packageDir, $packageRoot, 'external-updater', $filesystem );
				}
			);
		}
		finally {
			$filesystem->remove( $packageRoot );
		}
	}

	public function testRuntimeIdentityConstantsMatchPluginIdentity() :void {
		$this->assertSame( 'mandate-app-security', PluginIdentity::SLUG );
		$this->assertSame( 'mandate-app-security/', PluginIdentity::PACKAGE_ROOT );
		$this->assertSame( 'mandate-app-security-github', PluginIdentity::GITHUB_ASSET_PREFIX );
		$this->assertSame( 'mandate-github', PluginIdentity::LEGACY_GITHUB_ASSET_PREFIX );
		$this->assertSame( PluginIdentity::SLUG, RuntimePackageBuilder::PLUGIN_SLUG );
		$this->assertSame( PluginIdentity::MAIN_FILE, RuntimePackageBuilder::MAIN_PLUGIN_FILE );
	}

	public function testStaticWordPressIdentityFilesMatchPluginIdentity() :void {
		$projectRoot = \dirname( __DIR__, 2 );

		$this->assertFileExists( Path::join( $projectRoot, PluginIdentity::MAIN_FILE ) );
		$this->assertFileDoesNotExist( Path::join( $projectRoot, 'plugin.php' ) );

		$pluginHeader = $this->readProjectFile( PluginIdentity::MAIN_FILE );
		$this->assertMatchesRegularExpression( '/^\s*\*\s*Plugin Name:\s*'.\preg_quote( PluginIdentity::NAME, '/' ).'\s*$/m', $pluginHeader );
		$this->assertMatchesRegularExpression( '/^\s*\*\s*Text Domain:\s*'.\preg_quote( PluginIdentity::TEXT_DOMAIN, '/' ).'\s*$/m', $pluginHeader );

		$readme = $this->readProjectFile( 'readme.txt' );
		$this->assertStringContainsString( '=== '.PluginIdentity::NAME.' ===', $readme );
		$this->assertStringContainsString( 'Contributors: '.PluginIdentity::CONTRIBUTOR, $readme );
		$this->assertStringContainsString( '/wp-content/plugins/'.PluginIdentity::SLUG, $readme );
	}

	public function testStaticToolingIdentityFilesMatchPluginIdentity() :void {
		$releaseWorkflow = $this->readProjectFile( '.github/workflows/release.yml' );
		$this->assertStringContainsString( 'WORDPRESS_ORG_ZIP_NAME="'.PluginIdentity::SLUG.'-${GITHUB_REF_NAME}.zip"', $releaseWorkflow );
		$this->assertStringContainsString( 'GITHUB_ZIP_NAME="'.PluginIdentity::GITHUB_ASSET_PREFIX.'-${GITHUB_REF_NAME}.zip"', $releaseWorkflow );
		$this->assertStringContainsString( 'LEGACY_GITHUB_ZIP_NAME="'.PluginIdentity::LEGACY_GITHUB_ASSET_PREFIX.'-${GITHUB_REF_NAME}.zip"', $releaseWorkflow );
		$this->assertStringContainsString( 'composer build-zip -- --variant=github --skip-assets --output="$GITHUB_ZIP_NAME"', $releaseWorkflow );
		$this->assertStringContainsString( 'composer build-zip -- --variant=github --skip-assets --output="$LEGACY_GITHUB_ZIP_NAME"', $releaseWorkflow );
		$this->assertStringContainsString( 'php bin/verify-package.php --variant=wordpress-org --zip="${GITHUB_WORKSPACE}/build/${WORDPRESS_ORG_ZIP_NAME}"', $releaseWorkflow );
		$this->assertStringContainsString( 'php bin/verify-package.php --variant=github --zip="${GITHUB_WORKSPACE}/build/${GITHUB_ZIP_NAME}"', $releaseWorkflow );
		$this->assertStringContainsString( 'php bin/verify-package.php --variant=github --zip="${GITHUB_WORKSPACE}/build/${LEGACY_GITHUB_ZIP_NAME}"', $releaseWorkflow );
		$this->assertStringContainsString( '${{ github.workspace }}/build/${{ env.WORDPRESS_ORG_ZIP_NAME }}', $releaseWorkflow );
		$this->assertStringContainsString( '${{ github.workspace }}/build/${{ env.GITHUB_ZIP_NAME }}', $releaseWorkflow );
		$this->assertStringContainsString( '${{ github.workspace }}/build/${{ env.LEGACY_GITHUB_ZIP_NAME }}', $releaseWorkflow );

		$browserCompose = $this->readProjectFile( 'tests/docker/docker-compose.browser.yml' );
		$this->assertStringContainsString( '/wp-content/plugins/'.PluginIdentity::SLUG, $browserCompose );

		$pluginCheckCompose = $this->readProjectFile( 'tests/docker/docker-compose.plugin-check.yml' );
		$this->assertStringContainsString( '/wp-content/plugins/'.PluginIdentity::SLUG, $pluginCheckCompose );

		$browserProvision = $this->readProjectFile( 'tests/docker/provision-browser-site.sh' );
		$this->assertStringContainsString( 'PLUGIN_SLUG="'.PluginIdentity::SLUG.'"', $browserProvision );
		$this->assertStringContainsString( 'PLUGIN_MAIN="${PLUGIN_SLUG}/'.PluginIdentity::MAIN_FILE.'"', $browserProvision );

		$pluginCheckProvision = $this->readProjectFile( 'tests/plugin-check/provision-site.sh' );
		$this->assertStringContainsString( 'wp plugin activate '.PluginIdentity::SLUG, $pluginCheckProvision );

		$pluginCheckRunner = $this->readProjectFile( 'tests/plugin-check/run-plugin-check.php' );
		$this->assertStringContainsString( "'".PluginIdentity::SLUG."'", $pluginCheckRunner );
		$this->assertStringContainsString( "'--slug=".PluginIdentity::SLUG."'", $pluginCheckRunner );

		$browserSpec = $this->readProjectFile( 'tests/browser/mandate-admin.spec.js' );
		$this->assertStringContainsString( 'page='.PluginIdentity::SLUG, $browserSpec );

		$githubUpdater = $this->readProjectFile( 'infrastructure/templates/github-updater.php' );
		$this->assertStringContainsString( 'PluginIdentity::MAIN_FILE', $githubUpdater );
		$this->assertStringContainsString( 'PluginIdentity::SLUG', $githubUpdater );
		$this->assertStringContainsString( 'PluginIdentity::GITHUB_ASSET_PREFIXES', $githubUpdater );
	}

	private function buildProjectPackageZip(
		string $projectRoot,
		string $packageRoot,
		string $variant,
		string $zipName,
		Filesystem $filesystem
	) :string {
		$packageDir = Path::join( $packageRoot, $variant.'-'.\bin2hex( \random_bytes( 4 ) ), RuntimePackageBuilder::PLUGIN_SLUG );
		$zipPath = Path::join( $packageRoot, $zipName );

		$this->buildPackage( $projectRoot, $packageDir, \dirname( $packageDir ), $variant, $filesystem );
		( new ZipBuilder( $filesystem, static function ( string $message ) :void {} ) )
			->build( $packageDir, $zipPath, RuntimePackageBuilder::PLUGIN_SLUG );

		return $zipPath;
	}

	private function assertPackageZipHasEntry( string $zipPath, string $entry ) :void {
		$zip = new ZipArchive();
		$this->assertSame( true, $zip->open( $zipPath ) );
		try {
			$this->assertNotSame( false, $zip->locateName( $entry ), 'Missing package entry: '.$entry );
		}
		finally {
			$zip->close();
		}
	}

	private function assertPackageVerifierPasses( string $variant, string $zipPath ) :void {
		$result = $this->runPackageVerifier( $variant, $zipPath );

		$this->assertSame( 0, $result[ 'exit_code' ], $result[ 'output' ] );
	}

	/**
	 * @return array{exit_code:int,output:string}
	 */
	private function runPackageVerifier( string $variant, string $zipPath ) :array {
		$projectRoot = \dirname( __DIR__, 2 );
		$process = new Process(
			[
				PHP_BINARY,
				'bin/verify-package.php',
				'--variant='.$variant,
				'--zip='.$zipPath,
			],
			$projectRoot
		);
		$process->run();

		return [
			'exit_code' => $process->getExitCode() ?? -1,
			'output'    => $process->getOutput().$process->getErrorOutput(),
		];
	}

	private function buildPackage(
		string $projectRoot,
		string $packageDir,
		string $packageRoot,
		string $variant,
		Filesystem $filesystem
	) :void {
		$builder = new RuntimePackageBuilder(
			$projectRoot,
			new Wpm_Test_Package_CommandRunner( $projectRoot, static function ( string $message ) :void {} ),
			$filesystem,
			static function ( string $message ) :void {}
		);
		$builder->build( $packageDir, $packageRoot, false, $variant );
	}

	private function assertPackageComposerJsonContainsRuntimeConfigOnly( string $packageDir ) :void {
		$config = $this->readPackageComposerJson( $packageDir );
		$this->assertSame( [ 'php' => '>=8.1' ], $this->readPackageComposerRequire( $packageDir ) );
		$this->assertSame( [ 'psr-4' => [ 'Fixture\\' => 'src/' ] ], $config[ 'autoload' ] );
		$this->assertArrayNotHasKey( 'require-dev', $config );
		$this->assertArrayNotHasKey( 'autoload-dev', $config );
		$this->assertArrayNotHasKey( 'scripts', $config );
		$this->assertArrayNotHasKey( 'minimum-stability', $config );
	}

	private function assertPackageComposerJsonDoesNotRequireGithubUpdater( string $packageDir ) :void {
		$this->assertArrayNotHasKey( 'yahnis-elsts/plugin-update-checker', $this->readPackageComposerRequire( $packageDir ) );
	}

	private function assertPackageComposerJsonRequiresGithubUpdater( string $packageDir ) :void {
		$require = $this->readPackageComposerRequire( $packageDir );
		$this->assertArrayHasKey( 'yahnis-elsts/plugin-update-checker', $require );
		$this->assertSame( '^5.6', $require[ 'yahnis-elsts/plugin-update-checker' ] );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function readPackageComposerJson( string $packageDir ) :array {
		$json = \file_get_contents( Path::join( $packageDir, 'composer.json' ) );
		if ( !\is_string( $json ) ) {
			throw new RuntimeException( 'Failed to read package composer.json.' );
		}

		$config = \json_decode( $json, true );
		if ( !\is_array( $config ) ) {
			throw new RuntimeException( 'Package composer.json is not valid JSON.' );
		}

		return $config;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function readPackageComposerRequire( string $packageDir ) :array {
		$config = $this->readPackageComposerJson( $packageDir );
		$this->assertArrayHasKey( 'require', $config );
		$this->assertIsArray( $config[ 'require' ] );

		return $config[ 'require' ];
	}

	private function readPackageFile( string $packageDir, string $relativePath ) :string {
		$content = \file_get_contents( Path::join( $packageDir, $relativePath ) );
		if ( !\is_string( $content ) ) {
			throw new RuntimeException( 'Failed to read package file: '.$relativePath );
		}

		return $content;
	}

	private function readProjectFile( string $relativePath ) :string {
		$content = \file_get_contents( Path::join( \dirname( __DIR__, 2 ), $relativePath ) );
		if ( !\is_string( $content ) ) {
			throw new RuntimeException( 'Failed to read project file: '.$relativePath );
		}

		return $content;
	}

	private function assertPackageDoesNotContainGithubUpdaterTokens( string $packageDir ) :void {
		$tokens = [
			'YahnisElsts',
			'PluginUpdateChecker',
			'PucFactory',
			'plugin-update-checker',
			...array_map(
				static fn( string $assetPrefix ) :string => $assetPrefix.'-',
				PluginIdentity::GITHUB_ASSET_PREFIXES
			),
		];

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $packageDir, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $item ) {
			/** @var SplFileInfo $item */
			if ( !$item->isFile() ) {
				continue;
			}

			$content = \file_get_contents( $item->getPathname() );
			if ( !\is_string( $content ) ) {
				throw new RuntimeException( 'Failed to read package file: '.$item->getPathname() );
			}

			foreach ( $tokens as $token ) {
				$this->assertStringNotContainsString(
					$token,
					$content,
					'Unexpected GitHub updater token in '.Path::makeRelative( $item->getPathname(), $packageDir )
				);
			}
		}
	}

	private function createPackageFixture( string $fixtureRoot, Filesystem $filesystem ) :void {
		$filesystem->mkdir( [
			Path::join( $fixtureRoot, 'src' ),
			Path::join( $fixtureRoot, 'assets/dist' ),
			Path::join( $fixtureRoot, 'site/assets' ),
			Path::join( $fixtureRoot, 'infrastructure/templates' ),
		] );

		$filesystem->dumpFile( Path::join( $fixtureRoot, RuntimePackageBuilder::MAIN_PLUGIN_FILE ), "<?php\n/*\n * Plugin Name: Fixture\n * Plugin URI: https://example.test\n * Version: 1.0.0\n */\n" );
		$filesystem->dumpFile( Path::join( $fixtureRoot, 'init.php' ), "<?php declare( strict_types=1 );\n\nuse Fixture\\Plugin;\n\n\\call_user_func( function () {\n\t\$mandate_app_security_autoload = __DIR__.'/vendor/autoload.php';\n\tif ( \\is_file( \$mandate_app_security_autoload ) ) {\n\t\trequire_once \$mandate_app_security_autoload;\n\t\tPlugin::boot( __DIR__.'/".RuntimePackageBuilder::MAIN_PLUGIN_FILE."' );\n\t}\n} );\n" );
		$filesystem->dumpFile( Path::join( $fixtureRoot, 'infrastructure/templates/github-updater.php' ), "<?php\nuse YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory;\n" );

		foreach ( [
			'unsupported.php',
			'readme.txt',
			'LICENSE',
			'src/Plugin.php',
			'assets/dist/admin-page.css',
			'assets/dist/admin-page.js',
			'assets/dist/info-square.svg',
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
