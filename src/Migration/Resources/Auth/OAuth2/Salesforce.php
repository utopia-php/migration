<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Salesforce extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'salesforce';
    }
}
