<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Stripe extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'stripe';
    }
}
