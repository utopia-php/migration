<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Etsy extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_ETSY;
    }

    public static function getProviderKey(): string
    {
        return 'etsy';
    }
}
