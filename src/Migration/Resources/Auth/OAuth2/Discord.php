<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Discord extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_DISCORD;
    }

    public static function getProviderKey(): string
    {
        return 'discord';
    }
}
