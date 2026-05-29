<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Spotify extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'spotify';
    }
}
