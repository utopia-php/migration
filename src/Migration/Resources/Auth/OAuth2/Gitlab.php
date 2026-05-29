<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Gitlab extends WithEndpointProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_GITLAB;
    }

    public static function getProviderKey(): string
    {
        return 'gitlab';
    }
}
