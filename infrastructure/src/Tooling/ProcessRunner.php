<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Tooling;

use Symfony\Component\Process\Process;

class ProcessRunner {

	/**
	 * @param string[] $command
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 * @param array<string,string|false>|null $envOverrides
	 */
	public function run(
		array $command,
		string $workingDir,
		?callable $onOutput = null,
		?array $envOverrides = null
	) :Process {
		$this->assertWorkingDir( $workingDir );
		$this->writeCommand( $command );

		$process = $this->newProcess( $command, $workingDir, $envOverrides );
		$process->run( $onOutput ?? function ( string $type, string $buffer ) :void {
			$this->writeOutputBuffer( $type, $buffer );
		} );

		return $process;
	}

	/**
	 * @param string[] $command
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 * @param array<string,string|false>|null $envOverrides
	 */
	public function runOrThrow(
		array $command,
		string $workingDir,
		?callable $onOutput = null,
		?array $envOverrides = null
	) :Process {
		$process = $this->run( $command, $workingDir, $onOutput, $envOverrides );
		if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
			throw new \RuntimeException( $this->failureMessage( $command, $process ) );
		}

		return $process;
	}

	/**
	 * @param string[] $command
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 * @param array<string,string|false>|null $envOverrides
	 */
	public function runForExitCode(
		array $command,
		string $workingDir,
		?callable $onOutput = null,
		?array $envOverrides = null
	) :int {
		return $this->run( $command, $workingDir, $onOutput, $envOverrides )->getExitCode() ?? 1;
	}

	/**
	 * @param string[] $command
	 * @param array<string,string|false>|null $envOverrides
	 * @return array{exit_code:int,stdout:string,stderr:string}
	 */
	public function runAndCapture(
		array $command,
		string $workingDir,
		?array $envOverrides = null
	) :array {
		$process = $this->run(
			$command,
			$workingDir,
			static function () :void {},
			$envOverrides
		);

		return [
			'exit_code' => $process->getExitCode() ?? 1,
			'stdout'    => $process->getOutput(),
			'stderr'    => $process->getErrorOutput(),
		];
	}

	/**
	 * @param array<int,array{command:string[],working_dir:string,env?:array<string,string|false>,label?:string}> $jobs
	 * @return array<int,array{exit_code:int,stdout:string,stderr:string,label:string}>
	 */
	public function runConcurrent( array $jobs ) :array {
		if ( $jobs === [] ) {
			return [];
		}

		$running = [];
		foreach ( $jobs as $index => $job ) {
			$workingDir = $job[ 'working_dir' ];
			$this->assertWorkingDir( $workingDir );
			$this->writeCommand( $job[ 'command' ] );

			$process = $this->newProcess( $job[ 'command' ], $workingDir, $job[ 'env' ] ?? null );
			$process->start();
			$running[ $index ] = [
				'process' => $process,
				'label'   => (string)( $job[ 'label' ] ?? (string)$index ),
			];
		}

		do {
			$active = false;
			foreach ( $running as $processState ) {
				/** @var Process $process */
				$process = $processState[ 'process' ];
				$this->flushIncrementalOutput( $process );
				if ( $process->isRunning() ) {
					$active = true;
				}
			}
			if ( $active ) {
				\usleep( 100000 );
			}
		} while ( $active );

		$results = [];
		foreach ( $running as $index => $processState ) {
			/** @var Process $process */
			$process = $processState[ 'process' ];
			$process->wait();
			$this->flushIncrementalOutput( $process );
			$results[ $index ] = [
				'exit_code' => $process->getExitCode() ?? 1,
				'stdout'    => $process->getOutput(),
				'stderr'    => $process->getErrorOutput(),
				'label'     => $processState[ 'label' ],
			];
		}

		return $results;
	}

	/**
	 * @param string[] $command
	 * @param array<string,string|false>|null $envOverrides
	 */
	private function newProcess( array $command, string $workingDir, ?array $envOverrides ) :Process {
		$process = new Process(
			$command,
			$workingDir,
			$envOverrides,
			null,
			null
		);
		$process->setTimeout( null );

		return $process;
	}

	private function assertWorkingDir( string $workingDir ) :void {
		if ( !\is_dir( $workingDir ) ) {
			throw new \RuntimeException( 'Working directory does not exist: '.$workingDir );
		}
	}

	/**
	 * @param string[] $command
	 */
	private function writeCommand( array $command ) :void {
		echo '> '.\implode( ' ', \array_map( [ $this, 'quoteArg' ], $command ) ).\PHP_EOL;
	}

	private function writeOutputBuffer( string $type, string $buffer ) :void {
		if ( $type === Process::ERR ) {
			\fwrite( \STDERR, $buffer );
		}
		else {
			echo $buffer;
		}
	}

	private function flushIncrementalOutput( Process $process ) :void {
		$output = $process->getIncrementalOutput();
		if ( $output !== '' ) {
			echo $output;
		}

		$errorOutput = $process->getIncrementalErrorOutput();
		if ( $errorOutput !== '' ) {
			\fwrite( \STDERR, $errorOutput );
		}
	}

	/**
	 * @param string[] $command
	 */
	private function failureMessage( array $command, Process $process ) :string {
		$exitCode = $process->getExitCode() ?? 1;
		$message = \sprintf(
			'Command failed with exit code %d: %s',
			$exitCode,
			\implode( ' ', $command )
		);

		$errorOutput = \trim( $process->getErrorOutput() );
		if ( $errorOutput !== '' ) {
			$message .= \PHP_EOL.'Error output: '.$errorOutput;
		}

		return $message;
	}

	private function quoteArg( string $arg ) :string {
		return \preg_match( '/\s/', $arg ) === 1 ? '"'.$arg.'"' : $arg;
	}
}
