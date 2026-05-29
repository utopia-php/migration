<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Facebook extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_FACEBOOK;
    }

    public static function getProviderKey(): string
    {
        return 'facebook';
    }
}
