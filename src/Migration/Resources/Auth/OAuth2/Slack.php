<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Slack extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'slack';
    }
}
