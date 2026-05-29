<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Kick extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'kick';
    }
}
