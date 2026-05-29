<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

class Figma extends StandardProvider
{
    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_FIGMA;
    }

    public static function getProviderKey(): string
    {
        return 'figma';
    }
}
