<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Yahoo extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_YAHOO;
    }

    public static function getProviderKey(): string
    {
        return 'yahoo';
    }
}
