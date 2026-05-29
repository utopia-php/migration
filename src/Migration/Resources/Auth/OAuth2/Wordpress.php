<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Wordpress extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'wordpress';
    }
}
