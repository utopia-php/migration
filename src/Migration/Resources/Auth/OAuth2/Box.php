<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Box extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'box';
    }
}
