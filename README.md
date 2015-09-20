# Fastra

[![Build Status](https://travis-ci.org/atijust/fastra.svg)](https://travis-ci.org/atijust/fastra)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/atijust/fastra/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/atijust/fastra/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/atijust/fastra/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/atijust/fastra/?branch=master)
[![License](https://poser.pugx.org/atijust/fastra/license)](https://packagist.org/packages/atijust/fastra)

A micro-framework for PHP5.5+.

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = new Fastra\Application();
$app->group(function ($router) {
    $router->get('/{name}', function ($name) {
        return 'Hello, ' . $name;
    });
})->prefix('/hello')->middleware(AuthMiddleware::class);
$app->run();
```
