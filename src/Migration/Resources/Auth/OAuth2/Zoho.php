<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Zoho extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_ZOHO;
    }

    public static function getProviderKey(): string
    {
        return 'zoho';
    }
}
