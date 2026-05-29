<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Zoom extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_ZOOM;
    }

    public static function getProviderKey(): string
    {
        return 'zoom';
    }
}
