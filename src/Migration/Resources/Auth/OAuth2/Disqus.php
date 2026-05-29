<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Disqus extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'disqus';
    }
}
