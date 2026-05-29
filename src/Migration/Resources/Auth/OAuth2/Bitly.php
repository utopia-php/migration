<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Bitly extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_BITLY;
    }

    public static function getProviderKey(): string
    {
        return 'bitly';
    }
}
