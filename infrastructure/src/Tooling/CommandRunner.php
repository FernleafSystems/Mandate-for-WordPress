<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Tooling;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class CommandRunner {

	private string $projectRoot;

	/** @var callable(string):void */
	private $logger;

	/**
	 * @param callable(string):void $logger
	 */
	public function __construct( string $projectRoot, callable $logger ) {
		$this->projectRoot = Path::normalize( $projectRoot );
		$this->logger = $logger;
	}

	/**
	 * @param string[] $command
	 */
	public function run( array $command, ?string $workingDir = null ) :void {
		$cwd = $workingDir === null ? $this->projectRoot : Path::normalize( $workingDir );
		if ( !\is_dir( $cwd ) ) {
			throw new \RuntimeException( 'Working directory does not exist: '.$cwd );
		}

		$this->log( '> '.\implode( ' ', $command ) );

		$process = new Process(
			$command,
			$cwd,
			null,
			null,
			null
		);
		$process->setTimeout( null );
		$process->run( function ( string $type, string $buffer ) :void {
			if ( $type === Process::ERR ) {
				\fwrite( \STDERR, $buffer );
			}
			else {
				echo $buffer;
			}
		} );

		$exitCode = $process->getExitCode() ?? 1;
		if ( $exitCode !== 0 ) {
			$error = \trim( $process->getErrorOutput() );
			$message = \sprintf(
				'Command failed with exit code %d: %s',
				$exitCode,
				\implode( ' ', $command )
			);
			if ( $error !== '' ) {
				$message .= PHP_EOL.'Error output: '.$error;
			}

			throw new \RuntimeException( $message );
		}
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

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}
}
