<?php

namespace Utopia\Tests\Unit\Destinations;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\Document as UtopiaDocument;
use Utopia\Migration\Destinations\Appwrite as AppwriteDestination;
use Utopia\Migration\Destinations\OnDuplicate;
use Utopia\Migration\Resources\Database\Database as DatabaseResource;

/**
 * Regression for PR #151: the destination must never write the source's DSN
 * into `_databases.database`. With no resolver, the value must be blank so
 * the runtime falls back to the destination project's DSN. With a resolver,
 * the resolver's value must be written and the source's value ignored.
 *
 * Reproduces the comuneo-pre-production incident where post-migration
 * `_databases.database` rows pointed at the source's host (db11) and
 * destination reads hit `Table 'appwrite._<tenant>__metadata' doesn't exist`.
 */
class AppwriteDestinationDsnTest extends TestCase
{
    public function testWithoutResolverReturnsEmptyString(): void
    {
        $destination = $this->makeDestination(getDatabaseDSN: null);
        $resource = $this->makeResource(sourceDsn: 'database_db_fra1_self_hosted_11_0');

        $resolved = $this->invokeResolver($destination, $resource);

        $this->assertSame('', $resolved, 'Without a resolver the destination must not propagate the source DSN.');
    }

    public function testWithResolverUsesItsReturnValue(): void
    {
        $expected = 'appwrite://database_db_fra1_self_hosted_17_0?database=appwrite&namespace=_1';
        $destination = $this->makeDestination(
            getDatabaseDSN: fn (DatabaseResource $r): string => $expected,
        );
        $resource = $this->makeResource(sourceDsn: 'database_db_fra1_self_hosted_11_0');

        $resolved = $this->invokeResolver($destination, $resource);

        $this->assertSame($expected, $resolved);
        $this->assertNotSame($resource->getDatabase(), $resolved, 'Source DSN must not leak through the resolver path.');
    }

    public function testResolverReceivesTheResource(): void
    {
        $captured = null;
        $destination = $this->makeDestination(
            getDatabaseDSN: function (DatabaseResource $r) use (&$captured): string {
                $captured = $r;
                return 'resolved';
            },
        );
        $resource = $this->makeResource(sourceDsn: 'src');

        $this->invokeResolver($destination, $resource);

        $this->assertSame($resource, $captured);
    }

    private function makeDestination(?callable $getDatabaseDSN): AppwriteDestination
    {
        return new AppwriteDestination(
            project: 'destination-project',
            endpoint: 'http://example.test/v1',
            key: 'test-key',
            dbForProject: $this->createStub(UtopiaDatabase::class),
            getDatabasesDB: fn (UtopiaDocument $database): UtopiaDatabase => $this->createStub(UtopiaDatabase::class),
            collectionStructure: ['attributes' => [], 'indexes' => []],
            dbForPlatform: $this->createStub(UtopiaDatabase::class),
            projectInternalId: '1',
            onDuplicate: OnDuplicate::Fail,
            getDatabaseDSN: $getDatabaseDSN,
        );
    }

    private function makeResource(string $sourceDsn): DatabaseResource
    {
        return new DatabaseResource(
            id: 'src-database',
            name: 'src',
            type: 'legacy',
            database: $sourceDsn,
        );
    }

    private function invokeResolver(AppwriteDestination $destination, DatabaseResource $resource): string
    {
        $method = (new ReflectionClass(AppwriteDestination::class))->getMethod('resolveDestinationDsn');
        /** @var string $value */
        $value = $method->invoke($destination, $resource);
        return $value;
    }
}
