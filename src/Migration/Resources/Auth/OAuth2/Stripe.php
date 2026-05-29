<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Stripe extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_STRIPE;
    }

    public static function getProviderKey(): string
    {
        return 'stripe';
    }
}
