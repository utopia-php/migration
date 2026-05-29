<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Disqus extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_DISQUS;
    }

    public static function getProviderKey(): string
    {
        return 'disqus';
    }
}
