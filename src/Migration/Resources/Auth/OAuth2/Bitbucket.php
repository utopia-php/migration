<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Bitbucket extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'bitbucket';
    }
}
