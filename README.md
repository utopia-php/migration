# Utopia Migration

[![Build Status](https://travis-ci.com/utopia-php/migration.svg?branch=main)](https://travis-ci.com/utopia-php/migration)
![Total Downloads](https://img.shields.io/packagist/dt/utopia-php/migration.svg)
[![Discord](https://img.shields.io/discord/564160730845151244?label=discord)](https://appwrite.io/discord)

Utopia Migration is a simple and lite library to migrate and transform resources in-between services. This library is aiming to be as simple and easy to learn and use. This library is maintained by the [Appwrite team](https://appwrite.io).

Although this library is part of the [Utopia Framework](https://github.com/utopia-php/framework) project it is dependency free and can be used as standalone with any other PHP project or framework.

## Getting Started

Install using composer:
```bash
composer require utopia-php/migration
```

Init in your application:
```php
<?php

use Utopia\Migration\Transfer;
use Utopia\Migration\Sources\NHost;
use Utopia\Migration\Destinations\Appwrite;

require_once __DIR__ . '/../../vendor/autoload.php';

// Initialize your Source
$source = new NHost('db.xxxxxxxxx.nhost.run', 'database-name', 'username', 'password');

// Initialize your Destination
$destination = new Appwrite('project-id', 'https://cloud.appwrite.io/v1', 'api-key');

// Initialize Transfer
$migration = new Transfer($source, $destination);

// Transfer the resource groups you want
$transfer->run(
    [
        Transfer::GROUP_AUTH
    ], function ($status) {
        echo $status['message'] . PHP_EOL;
    }
);
```

## Appwrite database recovery

Appwrite database destinations use a `ProvisioningOwner` made from a stable logical migration identifier and a fresh attempt identifier for every execution. The required `getRecoverableOwner` callback is the recovery authority for an existing database whose status is `provisioning` or `failed`.

A database status is local to that resource. It does not prove that the migration attempt which owns it has stopped, because an import can continue with other resources after recording a database failure. The callback must therefore consult the caller's authoritative operation lifecycle and return the exact stored owner only after that attempt is terminal. Return `null` while it is active or unknown; recovery then fails closed. This rule also applies when the retry uses the same logical migration identifier.

The standalone CLI requires `--migration-id` and a fresh `--migration-attempt-id`. Recovering an incomplete database additionally requires both `--recover-migration-id` and `--recover-migration-attempt-id` for the exact terminal prior attempt.

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
> The Local destination should be used for testing purposes only. It is not recommended to use this destination in production or as a backup. The local destination is there to confirm that a source is working correctly and to test the migration process with needing a target destination instance. This may change in the future however as the library matures.



## System Requirements

Utopia Migration requires PHP 8.0 or later. We recommend using the latest PHP version whenever possible.

## Copyright and license

The MIT License (MIT) [http://www.opensource.org/licenses/mit-license.php](http://www.opensource.org/licenses/mit-license.php)
