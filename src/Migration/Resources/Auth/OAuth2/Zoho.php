<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Zoho extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'zoho';
    }
}
