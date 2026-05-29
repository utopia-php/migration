<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Bitly extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'bitly';
    }
}
