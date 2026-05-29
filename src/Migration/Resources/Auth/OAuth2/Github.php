<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Github extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'github';
    }
}
