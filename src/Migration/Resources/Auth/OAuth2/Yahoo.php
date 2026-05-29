<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Yahoo extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'yahoo';
    }
}
