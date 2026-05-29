<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Amazon extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'amazon';
    }
}
