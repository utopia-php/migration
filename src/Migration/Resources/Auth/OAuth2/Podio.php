<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Podio extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'podio';
    }
}
