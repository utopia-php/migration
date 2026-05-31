<?php

namespace Utopia\Tests\Unit\Destinations;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\Document as UtopiaDocument;
use Utopia\Migration\Destinations\Appwrite as AppwriteDestination;
use Utopia\Migration\Destinations\OnDuplicate;

/**
 * Unit coverage for the OAuth2 provider secret-merge helpers on the Appwrite
 * destination.
 *
 * Migration only carries the readable, non-secret fields (Apple's keyID/teamID,
 * and the endpoint/tenant/prompt extras); the credential the OAuth handshake
 * needs is write-only on the source and is re-entered by the destination admin.
 * These helpers must therefore overlay the migrated fields onto the
 * destination's existing secret blob WITHOUT clobbering whatever secret
 * material already lives there (e.g. Apple's p8), and must tolerate a malformed
 * or empty existing blob.
 */
class AppwriteOAuth2SecretTest extends TestCase
{
    public function testAppleSecretWritesKeyAndTeamOntoEmpty(): void
    {
        $merged = $this->invoke('mergeAppleSecret', ['', 'KEY123', 'TEAM456']);

        $this->assertSame(
            ['keyID' => 'KEY123', 'teamID' => 'TEAM456'],
            \json_decode($merged, true),
        );
    }

    public function testAppleSecretPreservesExistingP8(): void
    {
        $existing = \json_encode(['p8' => 'PRIVATE', 'keyID' => 'OLD']);
        $merged = $this->invoke('mergeAppleSecret', [$existing, 'NEW', 'TEAM']);

        $this->assertSame(
            ['p8' => 'PRIVATE', 'keyID' => 'NEW', 'teamID' => 'TEAM'],
            \json_decode($merged, true),
            'Destination p8 must survive; migrated keyID/teamID overlay the rest.',
        );
    }

    public function testAppleSecretSkipsBlankFields(): void
    {
        $existing = \json_encode(['p8' => 'PRIVATE']);
        $merged = $this->invoke('mergeAppleSecret', [$existing, '', '']);

        $this->assertSame(['p8' => 'PRIVATE'], \json_decode($merged, true));
    }

    public function testAppleSecretTreatsNonArrayExistingAsEmpty(): void
    {
        // A malformed/scalar secret on the destination must not break the merge.
        $merged = $this->invoke('mergeAppleSecret', ['"scalar"', 'KEY', 'TEAM']);

        $this->assertSame(['keyID' => 'KEY', 'teamID' => 'TEAM'], \json_decode($merged, true));
    }

    public function testJsonSecretMergesAndPreservesExistingKeys(): void
    {
        $existing = \json_encode(['clientSecret' => 'KEEP']);
        $merged = $this->invoke('mergeJsonSecret', [$existing, ['tenant' => 'contoso']]);

        $this->assertSame(
            ['clientSecret' => 'KEEP', 'tenant' => 'contoso'],
            \json_decode($merged, true),
        );
    }

    public function testJsonSecretTreatsNonArrayExistingAsEmpty(): void
    {
        $merged = $this->invoke('mergeJsonSecret', ['5', ['endpoint' => 'https://idp.example']]);

        $this->assertSame(['endpoint' => 'https://idp.example'], \json_decode($merged, true));
    }

    /**
     * Build a destination with stubbed DB dependencies (the secret-merge
     * helpers touch none of them) and invoke the private method by reflection,
     * matching AppwriteDestinationDsnTest's approach.
     *
     * @param array<int, mixed> $args
     */
    private function invoke(string $method, array $args): string
    {
        $destination = new AppwriteDestination(
            project: 'destination-project',
            endpoint: 'http://example.test/v1',
            key: 'test-key',
            dbForProject: $this->createStub(UtopiaDatabase::class),
            getDatabasesDB: fn (UtopiaDocument $database): UtopiaDatabase => $this->createStub(UtopiaDatabase::class),
            collectionStructure: ['attributes' => [], 'indexes' => []],
            dbForPlatform: $this->createStub(UtopiaDatabase::class),
            projectInternalId: '1',
            onDuplicate: OnDuplicate::Fail,
        );

        $reflection = (new ReflectionClass(AppwriteDestination::class))->getMethod($method);
        /** @var string $value */
        $value = $reflection->invoke($destination, ...$args);

        return $value;
    }
}
