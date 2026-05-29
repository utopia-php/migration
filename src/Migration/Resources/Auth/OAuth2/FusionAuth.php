<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class FusionAuth extends WithEndpointProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_FUSIONAUTH;
    }

    public static function getProviderKey(): string
    {
        return 'fusionauth';
    }
}
