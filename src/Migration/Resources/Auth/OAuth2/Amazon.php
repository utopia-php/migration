<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Amazon extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_AMAZON;
    }

    public static function getProviderKey(): string
    {
        return 'amazon';
    }
}
