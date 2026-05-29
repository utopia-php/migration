<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Kick extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_KICK;
    }

    public static function getProviderKey(): string
    {
        return 'kick';
    }
}
