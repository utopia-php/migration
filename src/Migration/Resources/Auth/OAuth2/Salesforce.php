<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Salesforce extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_SALESFORCE;
    }

    public static function getProviderKey(): string
    {
        return 'salesforce';
    }
}
