<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Dailymotion extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_DAILYMOTION;
    }

    public static function getProviderKey(): string
    {
        return 'dailymotion';
    }
}
