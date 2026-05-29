<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Podio extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_PODIO;
    }

    public static function getProviderKey(): string
    {
        return 'podio';
    }
}
