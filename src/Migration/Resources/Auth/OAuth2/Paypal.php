<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Paypal extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'paypal';
    }
}
