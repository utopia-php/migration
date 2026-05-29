<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Box extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_BOX;
    }

    public static function getProviderKey(): string
    {
        return 'box';
    }
}
