# Fastra

A micro-framework for PHP5.5+.

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = new Fastra\Application();
$app->get('/{name}', function ($name) {
    return 'Hello, ' . $name;
});
$app->run();
```
