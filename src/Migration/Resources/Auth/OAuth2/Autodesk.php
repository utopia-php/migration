<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Autodesk extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_AUTODESK;
    }

    public static function getProviderKey(): string
    {
        return 'autodesk';
    }
}
