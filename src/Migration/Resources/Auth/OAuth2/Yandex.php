<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Yandex extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_YANDEX;
    }

    public static function getProviderKey(): string
    {
        return 'yandex';
    }
}
