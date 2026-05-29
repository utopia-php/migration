<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Linkedin extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_LINKEDIN;
    }

    public static function getProviderKey(): string
    {
        return 'linkedin';
    }
}
