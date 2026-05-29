<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Dailymotion extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'dailymotion';
    }
}
