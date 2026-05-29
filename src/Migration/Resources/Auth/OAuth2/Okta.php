<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Okta extends WithEndpointProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_OKTA;
    }

    public static function getProviderKey(): string
    {
        return 'okta';
    }
}
