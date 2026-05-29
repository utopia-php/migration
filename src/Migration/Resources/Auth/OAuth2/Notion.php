<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Notion extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_NOTION;
    }

    public static function getProviderKey(): string
    {
        return 'notion';
    }
}
