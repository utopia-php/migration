<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class FusionAuth extends WithEndpointProvider
{
    public static function getProviderKey(): string
    {
        return 'fusionauth';
    }
}
