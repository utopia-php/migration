<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Okta extends WithEndpointProvider
{
    public static function getProviderKey(): string
    {
        return 'okta';
    }
}
