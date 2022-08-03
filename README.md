# dotenv.php

[![Latest Stable Version](http://poser.pugx.org/razxc/dotenv/v)](https://packagist.org/packages/razxc/dotenv)
[![Total Downloads](http://poser.pugx.org/razxc/dotenv/downloads)](https://packagist.org/packages/razxc/dotenv)
[![License](http://poser.pugx.org/razxc/dotenv/license)](https://packagist.org/packages/razxc/dotenv)
[![PHP Version Require](http://poser.pugx.org/razxc/dotenv/require/php)](https://packagist.org/packages/razxc/dotenv)


The PHP library provides pure dot-env parser
with bash syntax support and zero-dependencies.
Required PHP 7.3 or higher.

Alternatives:
[symfony/dotenv](https://github.com/symfony/dotenv),
[vlucas/phpdotenv](https://github.com/vlucas/phpdotenv)


***


## Feature

- "export" notation
- default values
- multiline values
- variable resolving


## Installation

```shell
composer require razxc/dotenv
```


## Example

```php
$array = DotenvParser::fromFile('path/to/.env', $_ENV);

foreach ($array as $name => $value) {
    putenv("$name=$value");
}

foreach ($array as $name => $value) {
    print $name . ' => ' . getenv($name) . PHP_EOL;
}
```



## TODO

- command resolving
