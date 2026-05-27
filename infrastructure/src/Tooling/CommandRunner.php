<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Tooling;

use Symfony\Component\Filesystem\Path;

class CommandRunner {

	private string $projectRoot;

	private ProcessRunner $processRunner;

	public function __construct( string $projectRoot ) {
		$this->projectRoot = Path::normalize( $projectRoot );
		$this->processRunner = new ProcessRunner();
	}

	/**
	 * @param string[] $command
	 */
	public function run( array $command, ?string $workingDir = null ) :void {
		$cwd = $workingDir === null ? $this->projectRoot : Path::normalize( $workingDir );
		if ( !\is_dir( $cwd ) ) {
			throw new \RuntimeException( 'Working directory does not exist: '.$cwd );
		}

		$this->processRunner->runOrThrow( $command, $cwd );
	}

	/**
	 * @return string[]
	 */
	public function getComposerCommand() :array {
		$binary = \getenv( 'COMPOSER_BINARY' );
		if ( !\is_string( $binary ) || $binary === '' ) {
			$binary = 'composer';
		}

		$resolved = $this->resolveBinaryPath( $binary );
		if ( \substr( $resolved, -5 ) === '.phar' ) {
			return [ \PHP_BINARY ?: 'php', $resolved ];
		}

		return [ $resolved ];
	}

	private function resolveBinaryPath( string $binary ) :string {
		$binary = \trim( $binary, " \t\n\r\0\x0B\"'" );

		if ( $binary !== '' && Path::isAbsolute( $binary ) && \file_exists( $binary ) ) {
			return $binary;
		}

		if ( \str_contains( $binary, '/' ) || \str_contains( $binary, '\\' ) ) {
			$fromRoot = Path::join( $this->projectRoot, $binary );
			if ( \file_exists( $fromRoot ) ) {
				return $fromRoot;
			}
		}

		$runtimeDir = \getenv( 'COMPOSER_RUNTIME_BIN_DIR' );
		if ( \is_string( $runtimeDir ) && $runtimeDir !== '' ) {
			$candidate = Path::join( \rtrim( $runtimeDir, '/\\' ), $binary );
			if ( \file_exists( $candidate ) ) {
				return $candidate;
			}
		}

		return $binary;
	}
}
