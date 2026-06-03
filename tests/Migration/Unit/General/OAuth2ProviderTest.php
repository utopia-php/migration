<?php

namespace Utopia\Tests\Unit\General;

use PHPUnit\Framework\TestCase;
use Utopia\Migration\Resources\Auth\OAuth2\OAuth2Provider;

class OAuth2ProviderTest extends TestCase
{
    public function testRoutesGoogleFieldsFromDescriptor(): void
    {
        $provider = OAuth2Provider::fromArray('google', [
            'id' => 'project-google',
            'enabled' => true,
            'clientId' => 'google-client',
            'prompt' => ['consent', 'select_account'],
            'clientSecret' => 'secret-must-not-migrate',
        ]);

        $this->assertInstanceOf(OAuth2Provider::class, $provider);
        $this->assertSame('google-client', $provider->getDestinationAppId());
        $this->assertSame(['prompt' => ['consent', 'select_account']], $provider->getDestinationSecretFields());
        $this->assertArrayNotHasKey('clientSecret', $provider->getSettings());
        $this->assertTrue($provider->isConfigured());
    }

    public function testRoutesAppleFieldsToDestinationSecretNames(): void
    {
        $provider = OAuth2Provider::fromArray('apple', [
            'id' => 'project-apple',
            'enabled' => false,
            'serviceId' => 'service-id',
            'keyId' => 'KEY123',
            'teamId' => 'TEAM456',
            'p8File' => 'secret-must-not-migrate',
        ]);

        $this->assertInstanceOf(OAuth2Provider::class, $provider);
        $this->assertSame('service-id', $provider->getDestinationAppId());
        $this->assertSame(['keyID' => 'KEY123', 'teamID' => 'TEAM456'], $provider->getDestinationSecretFields());
        $this->assertArrayNotHasKey('p8File', $provider->getSettings());
        $this->assertTrue($provider->isConfigured());
    }

    public function testUnknownProviderReturnsNull(): void
    {
        $this->assertNull(OAuth2Provider::fromArray('unknown', [
            'id' => 'project-unknown',
            'enabled' => true,
            'clientId' => 'client',
        ]));
    }

    public function testConfiguredWhenEnabledEvenWithoutAppId(): void
    {
        $provider = OAuth2Provider::fromArray('github', [
            'id' => 'project-github',
            'enabled' => true,
            'clientId' => '',
        ]);

        $this->assertInstanceOf(OAuth2Provider::class, $provider);
        $this->assertTrue($provider->isConfigured());
    }

    public function testSkipsDisabledProviderWithoutAppId(): void
    {
        $provider = OAuth2Provider::fromArray('github', [
            'id' => 'project-github',
            'enabled' => false,
            'clientId' => '',
        ]);

        $this->assertInstanceOf(OAuth2Provider::class, $provider);
        $this->assertFalse($provider->isConfigured());
    }
}
