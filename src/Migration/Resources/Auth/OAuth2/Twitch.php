<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Twitch extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_TWITCH;
    }

    public static function getProviderKey(): string
    {
        return 'twitch';
    }
}
