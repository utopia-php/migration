<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Linkedin extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'linkedin';
    }
}
