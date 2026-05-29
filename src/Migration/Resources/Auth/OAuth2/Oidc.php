<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Oidc extends WithEndpointProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_OIDC;
    }

    public static function getProviderKey(): string
    {
        return 'oidc';
    }
}
