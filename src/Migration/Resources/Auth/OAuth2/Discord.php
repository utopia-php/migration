<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Discord extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'discord';
    }
}
