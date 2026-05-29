<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Spotify extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_SPOTIFY;
    }

    public static function getProviderKey(): string
    {
        return 'spotify';
    }
}
