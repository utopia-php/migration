<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Authentik extends WithEndpointProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_AUTHENTIK;
    }

    public static function getProviderKey(): string
    {
        return 'authentik';
    }
}
