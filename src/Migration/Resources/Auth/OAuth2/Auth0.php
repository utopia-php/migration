<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Auth0 extends WithEndpointProvider
{
    public static function getProviderKey(): string
    {
        return 'auth0';
    }
}
