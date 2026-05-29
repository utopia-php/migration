<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Keycloak extends WithEndpointProvider
{
    public static function getProviderKey(): string
    {
        return 'keycloak';
    }
}
