<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Github extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_GITHUB;
    }

    public static function getProviderKey(): string
    {
        return 'github';
    }
}
