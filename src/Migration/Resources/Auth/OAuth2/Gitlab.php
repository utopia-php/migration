<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Gitlab extends WithEndpointProvider
{
    public static function getProviderKey(): string
    {
        return 'gitlab';
    }
}
