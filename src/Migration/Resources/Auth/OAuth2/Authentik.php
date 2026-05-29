<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Authentik extends WithEndpointProvider
{
    public static function getProviderKey(): string
    {
        return 'authentik';
    }
}
