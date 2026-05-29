<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Paypal extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_PAYPAL;
    }

    public static function getProviderKey(): string
    {
        return 'paypal';
    }
}
