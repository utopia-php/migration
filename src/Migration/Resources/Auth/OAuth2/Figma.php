<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Figma extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'figma';
    }
}
