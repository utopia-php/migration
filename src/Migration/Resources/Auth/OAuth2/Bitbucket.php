<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Bitbucket extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_BITBUCKET;
    }

    public static function getProviderKey(): string
    {
        return 'bitbucket';
    }
}
