<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Tradeshift extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'tradeshift';
    }
}
