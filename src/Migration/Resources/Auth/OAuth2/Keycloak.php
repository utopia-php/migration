<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Keycloak extends WithEndpointProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_KEYCLOAK;
    }

    public static function getProviderKey(): string
    {
        return 'keycloak';
    }
}
