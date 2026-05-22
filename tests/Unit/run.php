<?php

declare( strict_types=1 );

require __DIR__.'/bootstrap.php';
require __DIR__.'/ScoperTest.php';

$classes = [
	ScoperTest::class,
];
$failures = 0;
$count = 0;

foreach ( $classes as $class ) {
	$test = new $class();
	foreach ( get_class_methods( $test ) as $method ) {
		if ( strncmp( $method, 'test', 4 ) !== 0 ) {
			continue;
		}

		$count++;
		try {
			$test->setUp();
			$test->{$method}();
			echo '.';
		}
		catch ( Throwable $throwable ) {
			$failures++;
			echo "F\n\n";
			echo $class.'::'.$method."\n";
			echo $throwable->getMessage()."\n";
		}
	}
}

echo "\n".$count.' tests, '.$failures." failures\n";
exit( $failures > 0 ? 1 : 0 );
