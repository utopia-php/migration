<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Slack extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_SLACK;
    }

    public static function getProviderKey(): string
    {
        return 'slack';
    }
}
