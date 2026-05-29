<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Dropbox extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_DROPBOX;
    }

    public static function getProviderKey(): string
    {
        return 'dropbox';
    }
}
