<?php

namespace Utopia\Tests\Unit\Destinations;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\Document as UtopiaDocument;
use Utopia\Migration\Destinations\Appwrite as AppwriteDestination;
use Utopia\Migration\Destinations\OnDuplicate;

class AppwriteOAuth2SecretTest extends TestCase
{
    public function testJsonSecretMergesAndPreservesExistingKeys(): void
    {
        $existing = \json_encode(['clientSecret' => 'KEEP']);
        $merged = $this->invokeMergeJsonSecret($existing, ['tenant' => 'contoso']);

        $this->assertSame(
            ['clientSecret' => 'KEEP', 'tenant' => 'contoso'],
            \json_decode($merged, true),
        );
    }

    public function testJsonSecretPreservesExistingWhenNoFieldsAreMigrated(): void
    {
        $existing = \json_encode(['p8' => 'PRIVATE']);

        $this->assertSame($existing, $this->invokeMergeJsonSecret($existing, []));
    }

    public function testJsonSecretTreatsNonArrayExistingAsEmpty(): void
    {
        $merged = $this->invokeMergeJsonSecret('5', ['endpoint' => 'https://idp.example']);

        $this->assertSame(['endpoint' => 'https://idp.example'], \json_decode($merged, true));
    }

    private function invokeMergeJsonSecret(string $existing, array $fields): string
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

        $method = (new ReflectionClass(AppwriteDestination::class))->getMethod('mergeJsonSecret');
        /** @var string $value */
        $value = $method->invoke($destination, $existing, $fields);

        return $value;
    }
}
