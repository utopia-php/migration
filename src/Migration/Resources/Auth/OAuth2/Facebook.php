<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Facebook extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'facebook';
    }
}
