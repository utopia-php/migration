<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

class Yandex extends StandardProvider
{
    public static function getProviderKey(): string
    {
        return 'yandex';
    }
}
