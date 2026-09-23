<?php

namespace Utopia\Tests\Unit\General;

use Closure;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;
use Utopia\Migration\Exception;
use Utopia\Migration\Exception\Aborted;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Auth\User;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Table;
use Utopia\Migration\Transfer;
use Utopia\Tests\Unit\Adapters\MockDestination;
use Utopia\Tests\Unit\Adapters\MockSource;

final class TransferAbortTest extends TestCase
{
    /**
     * @param Closure(Transfer, callable): void $run
     */
    #[DataProvider('runProvider')]
    public function testAbortSwallowedBySourceStillStopsTheTransfer(Closure $run): void
    {
        $abort = new class ('Migration attempt was superseded') extends Aborted {
        };
        $source = $this->swallowingSource();
        $transfer = new Transfer($source, new MockDestination());
        $calls = 0;

        try {
            $run($transfer, static function () use ($abort, &$calls): never {
                $calls++;

                throw $abort;
            });
            $this->fail('The abort did not stop the transfer.');
        } catch (Aborted $caught) {
            $this->assertSame($abort, $caught);
        }

        $this->assertSame(1, $calls, 'The callback must not run again once it aborted.');
    }

    /**
     * @return array<string, array{Closure(Transfer, callable): void}>
     */
    public static function runProvider(): array
    {
        return [
            'run' => [
                static fn (Transfer $transfer, callable $callback) => $transfer->run(
                    [Resource::TYPE_USER, Resource::TYPE_DATABASE, Resource::TYPE_TABLE],
                    $callback,
                ),
            ],
            'resource selector' => [
                static fn (Transfer $transfer, callable $callback) => $transfer->runWithResourceSelector(
                    [Resource::TYPE_DATABASE, Resource::TYPE_TABLE],
                    $callback,
                    resourceId: 'table',
                    resourceInternalId: '2',
                    resourceType: Resource::TYPE_TABLE,
                    parentResourceId: 'database',
                    parentResourceInternalId: '1',
                    parentResourceType: Resource::TYPE_DATABASE,
                ),
            ],
        ];
    }

    public function testAbortDoesNotCarryOverToTheNextRun(): void
    {
        $abort = new Aborted('Stopped');
        $source = $this->swallowingSource();
        $destination = new MockDestination();
        $transfer = new Transfer($source, $destination);

        try {
            $transfer->run([Resource::TYPE_USER], static fn () => throw $abort);
            $this->fail('The abort did not stop the transfer.');
        } catch (Aborted $caught) {
            $this->assertSame($abort, $caught);
        }

        $transfer->run([Resource::TYPE_DATABASE], static function (): void {
        });

        $this->assertSame(['database'], $destination->getResourceTypeData(Transfer::GROUP_DATABASES, Resource::TYPE_DATABASE));
    }

    /**
     * A source that records every failure of a resource type and moves on, the way the
     * library sources did before they rethrew aborts.
     */
    private function swallowingSource(): MockSource
    {
        $source = new class () extends MockSource {
            #[Override]
            protected function exportGroupAuth(int $batchSize, array $resources): void
            {
                foreach ($resources as $resource) {
                    try {
                        parent::exportGroupAuth($batchSize, [$resource]);
                    } catch (Throwable $error) {
                        $this->addError(new Exception($resource, Transfer::GROUP_AUTH, message: $error->getMessage(), previous: $error));
                    }
                }
            }

            #[Override]
            protected function exportGroupDatabases(int $batchSize, array $resources): void
            {
                foreach ($resources as $resource) {
                    try {
                        parent::exportGroupDatabases($batchSize, [$resource]);
                    } catch (Throwable $error) {
                        $this->addError(new Exception($resource, Transfer::GROUP_DATABASES, message: $error->getMessage(), previous: $error));
                    }
                }
            }
        };

        $database = new Database('database', 'Database');
        $source->pushMockResource(new User('user', 'user@example.com'));
        $source->pushMockResource($database);
        $source->pushMockResource(new Table($database, 'Table', 'table'));

        return $source;
    }
}
