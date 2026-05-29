<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Oidc extends WithEndpointProvider
{
    public static function getProviderKey(): string
    {
        return 'oidc';
    }
}
