`kodus/sapi-host`
=================

This library implements a SAPI host for dispatch of PSR-15 `HandlerInterface`.

**This project is work in progress.**

Originally a fork of [Daniel Bannert](https://github.com/prisis)'s
[`narrowspark/http-emitter`](https://packagist.org/packages/narrowspark/http-emitter) package, this package
takes a different approach, internally leveraging [Tobias Nyholm](https://github.com/Nyholm)'s
[`nyholm/psr7-server`](https://packagist.org/packages/nyholm/psr7-server) package to bootstrap the
incoming PSR-7 Request.

The philosophy of this package is that hosting a *single* handler, for a *single* request, should be
a *single* operation.

## Usage

To bootstrap a `SapiHost`, you need to pick a [PSR-7](https://www.php-fig.org/psr/psr-7/) and
[PSR-17](https://www.php-fig.org/psr/psr-17/) implementation - for example, `nyholm/psr7-server`
supports both, and you can install it with:

    composer require nyholm/psr7-server

You need to have your [PSR-15](https://www.php-fig.org/psr/psr-15/) handler implementation to
dispatch, and then, for example, dispatch it from an `index.php` file, as follows:

```php
<?php

use Kodus\Http\SapiHost;
use Nyholm\Psr7\Factory\Psr17Factory;

$factory = new Psr17Factory();

$host = new SapiHost(
    $factory,
    $factory,
    $factory,
    $factory,
    $factory
);

$host->dispatch(new YourRequestHandler());
```

Note that `Psr17Factory` implements all of the required PSR-17 factory interfaces.
