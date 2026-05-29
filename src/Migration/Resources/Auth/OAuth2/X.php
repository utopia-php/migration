<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class X extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_X;
    }

    public static function getProviderKey(): string
    {
        return 'x';
    }
}
