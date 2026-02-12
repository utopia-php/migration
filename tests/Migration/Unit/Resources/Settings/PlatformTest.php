<?php

namespace Utopia\Tests\Unit\Resources\Settings;

use PHPUnit\Framework\TestCase;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Settings\Platform;
use Utopia\Migration\Transfer;

class PlatformTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $platform = new Platform(
            'platform-1',
            'web',
            'My Web App',
            '',
            '',
            'example.com',
        );

        $this->assertEquals('platform-1', $platform->getId());
        $this->assertEquals('web', $platform->getType());
        $this->assertEquals('My Web App', $platform->getPlatformName());
        $this->assertEquals('', $platform->getKey());
        $this->assertEquals('', $platform->getStore());
        $this->assertEquals('example.com', $platform->getHostname());
    }

    public function testMobilePlatform(): void
    {
        $platform = new Platform(
            'platform-2',
            'flutter-ios',
            'My iOS App',
            'com.example.app',
            'appstore-id-123',
            '',
        );

        $this->assertEquals('flutter-ios', $platform->getType());
        $this->assertEquals('com.example.app', $platform->getKey());
        $this->assertEquals('appstore-id-123', $platform->getStore());
        $this->assertEquals('', $platform->getHostname());
    }

    public function testGetName(): void
    {
        $this->assertEquals(Resource::TYPE_PLATFORM, Platform::getName());
    }

    public function testGetGroup(): void
    {
        $platform = new Platform('p1', 'web', 'Test');
        $this->assertEquals(Transfer::GROUP_SETTINGS, $platform->getGroup());
    }

    public function testFromArray(): void
    {
        $platform = Platform::fromArray([
            'id' => 'platform-3',
            'type' => 'android',
            'name' => 'Android App',
            'key' => 'com.example.android',
            'store' => '',
            'hostname' => '',
        ]);

        $this->assertEquals('platform-3', $platform->getId());
        $this->assertEquals('android', $platform->getType());
        $this->assertEquals('Android App', $platform->getPlatformName());
        $this->assertEquals('com.example.android', $platform->getKey());
    }

    public function testFromArrayWithDefaults(): void
    {
        $platform = Platform::fromArray([
            'id' => 'platform-4',
            'type' => 'web',
            'name' => 'Minimal Web',
        ]);

        $this->assertEquals('', $platform->getKey());
        $this->assertEquals('', $platform->getStore());
        $this->assertEquals('', $platform->getHostname());
    }

    public function testJsonSerialize(): void
    {
        $platform = new Platform(
            'platform-5',
            'web',
            'Serialized App',
            'key-val',
            'store-val',
            'host.com',
        );

        $json = $platform->jsonSerialize();

        $this->assertEquals('platform-5', $json['id']);
        $this->assertEquals('web', $json['type']);
        $this->assertEquals('Serialized App', $json['name']);
        $this->assertEquals('key-val', $json['key']);
        $this->assertEquals('store-val', $json['store']);
        $this->assertEquals('host.com', $json['hostname']);
    }
}
