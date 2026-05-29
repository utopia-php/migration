<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Etsy extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'etsy';
    }
}
