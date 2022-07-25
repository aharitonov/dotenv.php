<?php
use \net\lapaphp\dotenv\{DotenvParser, DotenvException};

require __DIR__ . '/../vendor/autoload.php';

try {
	$array = DotenvParser::fromFile(__DIR__ . '/env.demo', [
		'BASE_DIR' => 'some/dir'
	]);
} catch (DotenvException $e) {
	print "Error: " . $e;
}

foreach ($array as $name => $value) {
    putenv("$name=$value");
}

foreach ($array as $name => $value) {
    print $name . ' => ' . getenv($name) . PHP_EOL;
}
