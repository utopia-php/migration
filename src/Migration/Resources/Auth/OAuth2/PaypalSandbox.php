<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class PaypalSandbox extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'paypalSandbox';
    }
}
