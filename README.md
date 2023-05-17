# Utopia Transfer

[![Build Status](https://travis-ci.com/utopia-php/transfer.svg?branch=main)](https://travis-ci.com/utopia-php/transfer)
![Total Downloads](https://img.shields.io/packagist/dt/utopia-php/transfer.svg)
[![Discord](https://img.shields.io/discord/564160730845151244?label=discord)](https://appwrite.io/discord)

Utopia Transfer is a simple and lite library to transfer and translate resources inbetween services. This library is aiming to be as simple and easy to learn and use. This library is maintained by the [Appwrite team](https://appwrite.io).

Although this library is part of the [Utopia Framework](https://github.com/utopia-php/framework) project it is dependency free and can be used as standalone with any other PHP project or framework.

## Getting Started

Install using composer:
```bash
composer require utopia-php/transfer
```

Init in your application:
```php
<?php

use Utopia\Transfer\Transfer;
use Utopia\Transfer\Sources\NHost;
use Utopia\Transfer\Destinations\Appwrite;

require_once __DIR__ . '/../../vendor/autoload.php';

// Initialize your Source
$source = new NHost('db.xxxxxxxxx.nhost.run', 'database-name', 'username', 'password');

// Initialize your Destination
$destination = new Appwrite('project-id', 'https://cloud.appwrite.io/v1', 'api-key');

// Initialize Transfer
$transfer = new Transfer($source, $destination);

// Transfer the resource groups you want
$transfer->run(
    [
        Transfer::GROUP_AUTH
    ], function ($status) {
        echo $status['message'] . PHP_EOL;
    }
);
```

## Supported Resources Chart

Sources:
|          | Auth | Databases | Storage | Functions | Settings |
|----------|-------|-----------|-------|-----------|-----------|
| Appwrite |   ✅   |     ✅     |     ✅     |   ✅   |          |
| Supabase |   ✅   |     ✅     |     ✅     |       |           |
| NHost    |   ✅   |     ✅     |     ✅     |       |           |
| Firebase |   ✅   |     ✅     |     ✅     |       |           |

Destinations:
|          | Auth | Databases | Storage | Functions | Settings |
|----------|-------|-----------|-------|-----------|-----------|
| Appwrite |   ✅   |     ✅     |   ✅   |     ✅     |          |
| Local    |   ✅   |     ✅     |   ✅   |     ✅     |     ✅     |

> **Warning**
> The Local destination should be used for testing purposes only. It is not recommended to use this destination in production or as a backup. The local destination is there to confirm that a source is working correctly and to test the transfer process with needing a target destination instance. This may change in the future however as the library matures.



## System Requirements

Utopia Transfer requires PHP 8.0 or later. We recommend using the latest PHP version whenever possible.

## Authors

**Bradley Schofield**

+ [https://github.com/PineappleIOnic](https://github.com/PineappleIOnic)

## Copyright and license

The MIT License (MIT) [http://www.opensource.org/licenses/mit-license.php](http://www.opensource.org/licenses/mit-license.php)
