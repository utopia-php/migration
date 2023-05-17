<?php

namespace Utopia\Tests;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Transfer;

class ConcreteResource extends Resource
{
    public static function getName(): string
    {
        return 'TestResource';
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_GENERAL;
    }

    public function asArray(): array
    {
        return [];
    }
}
