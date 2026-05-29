<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Auth0 extends WithEndpointProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_AUTH0;
    }

    public static function getProviderKey(): string
    {
        return 'auth0';
    }
}
