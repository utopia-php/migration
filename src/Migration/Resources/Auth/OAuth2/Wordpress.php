<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Wordpress extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_WORDPRESS;
    }

    public static function getProviderKey(): string
    {
        return 'wordpress';
    }
}
