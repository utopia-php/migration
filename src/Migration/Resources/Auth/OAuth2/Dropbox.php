<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Dropbox extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'dropbox';
    }
}
