<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Zoom extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'zoom';
    }
}
