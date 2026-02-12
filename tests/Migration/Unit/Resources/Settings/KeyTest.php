<?php

namespace Utopia\Tests\Unit\Resources\Settings;

use PHPUnit\Framework\TestCase;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Settings\Key;
use Utopia\Migration\Transfer;

class KeyTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $key = new Key(
            'key-1',
            'My API Key',
            ['users.read', 'users.write', 'databases.read'],
            'secret-abc-123',
            '2025-12-31T23:59:59.000+00:00',
            '2025-01-15T10:30:00.000+00:00',
            ['console'],
        );

        $this->assertEquals('key-1', $key->getId());
        $this->assertEquals('My API Key', $key->getKeyName());
        $this->assertEquals(['users.read', 'users.write', 'databases.read'], $key->getScopes());
        $this->assertEquals('secret-abc-123', $key->getSecret());
        $this->assertEquals('2025-12-31T23:59:59.000+00:00', $key->getExpire());
        $this->assertEquals('2025-01-15T10:30:00.000+00:00', $key->getAccessedAt());
        $this->assertEquals(['console'], $key->getSdks());
    }

    public function testConstructorWithDefaults(): void
    {
        $key = new Key(
            'key-2',
            'Minimal Key',
            ['files.read'],
            'secret-xyz',
        );

        $this->assertNull($key->getExpire());
        $this->assertNull($key->getAccessedAt());
        $this->assertEquals([], $key->getSdks());
    }

    public function testGetName(): void
    {
        $this->assertEquals(Resource::TYPE_KEY, Key::getName());
    }

    public function testGetGroup(): void
    {
        $key = new Key('k1', 'Test', ['users.read'], 'secret');
        $this->assertEquals(Transfer::GROUP_SETTINGS, $key->getGroup());
    }

    public function testFromArray(): void
    {
        $key = Key::fromArray([
            'id' => 'key-3',
            'name' => 'From Array Key',
            'scopes' => ['functions.read', 'functions.write'],
            'secret' => 'secret-from-array',
            'expire' => '2026-06-30T00:00:00.000+00:00',
            'accessedAt' => '2025-03-01T12:00:00.000+00:00',
            'sdks' => ['flutter', 'web'],
        ]);

        $this->assertEquals('key-3', $key->getId());
        $this->assertEquals('From Array Key', $key->getKeyName());
        $this->assertEquals(['functions.read', 'functions.write'], $key->getScopes());
        $this->assertEquals('secret-from-array', $key->getSecret());
        $this->assertEquals('2026-06-30T00:00:00.000+00:00', $key->getExpire());
        $this->assertEquals(['flutter', 'web'], $key->getSdks());
    }

    public function testFromArrayWithDefaults(): void
    {
        $key = Key::fromArray([
            'id' => 'key-4',
            'name' => 'Minimal',
            'scopes' => ['users.read'],
            'secret' => 'min-secret',
        ]);

        $this->assertNull($key->getExpire());
        $this->assertNull($key->getAccessedAt());
        $this->assertEquals([], $key->getSdks());
    }

    public function testJsonSerialize(): void
    {
        $key = new Key(
            'key-5',
            'Serialized Key',
            ['buckets.read', 'buckets.write'],
            'secret-serial',
            '2025-12-31T00:00:00.000+00:00',
            null,
            ['android'],
        );

        $json = $key->jsonSerialize();

        $this->assertEquals('key-5', $json['id']);
        $this->assertEquals('Serialized Key', $json['name']);
        $this->assertEquals(['buckets.read', 'buckets.write'], $json['scopes']);
        $this->assertEquals('secret-serial', $json['secret']);
        $this->assertEquals('2025-12-31T00:00:00.000+00:00', $json['expire']);
        $this->assertNull($json['accessedAt']);
        $this->assertEquals(['android'], $json['sdks']);
    }
}
