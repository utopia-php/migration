<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Twitch extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'twitch';
    }
}
