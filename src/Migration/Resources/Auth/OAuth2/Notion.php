<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Notion extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'notion';
    }
}
