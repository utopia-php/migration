<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class TradeshiftSandbox extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'tradeshiftBox';
    }
}
