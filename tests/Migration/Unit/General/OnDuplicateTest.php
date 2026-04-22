<?php

namespace Migration\Unit\General;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Utopia\Migration\Destinations\OnDuplicate;
use Utopia\Migration\Destinations\SchemaAction;

/**
 * OnDuplicate::resolveSchemaAction is the load-bearing decision point for
 * re-migration tolerance. These tests lock the mode × existence × timestamp
 * matrix so a future refactor can't silently shift the mapping.
 */
class OnDuplicateTest extends TestCase
{
    public function testNotExistsAlwaysCreates(): void
    {
        foreach (OnDuplicate::cases() as $mode) {
            $this->assertSame(
                SchemaAction::Create,
                $mode->resolveSchemaAction(exists: false),
                "{$mode->value} on non-existing resource must return Create",
            );
        }
    }

    public function testFailExistsReturnsCreateSoCallerDDLThrows(): void
    {
        // Fail is routed through Create (not Tolerate) so the caller's normal
        // create flow runs and the library surfaces DuplicateException as
        // designed. Returning Tolerate here would silently hide the error.
        $this->assertSame(
            SchemaAction::Create,
            OnDuplicate::Fail->resolveSchemaAction(exists: true),
        );
    }

    public function testSkipAlwaysToleratesExisting(): void
    {
        // Skip must ignore timestamps entirely — it's the "don't touch"
        // contract. Exercise both orderings to prove the comparison isn't
        // consulted.
        $this->assertSame(
            SchemaAction::Tolerate,
            OnDuplicate::Skip->resolveSchemaAction(
                exists: true,
                sourceUpdatedAt: '2026-01-01T00:00:00.000+00:00',
                destUpdatedAt: '2020-01-01T00:00:00.000+00:00',
            ),
        );
        $this->assertSame(
            SchemaAction::Tolerate,
            OnDuplicate::Skip->resolveSchemaAction(
                exists: true,
                sourceUpdatedAt: '2020-01-01T00:00:00.000+00:00',
                destUpdatedAt: '2026-01-01T00:00:00.000+00:00',
            ),
        );
    }

    public function testUpsertSourceStrictlyNewerReconciles(): void
    {
        $this->assertSame(
            SchemaAction::DropAndRecreate,
            OnDuplicate::Upsert->resolveSchemaAction(
                exists: true,
                sourceUpdatedAt: '2026-04-23T10:00:00.000+00:00',
                destUpdatedAt: '2026-04-23T09:59:59.000+00:00',
            ),
        );
    }

    public function testUpsertDestNewerTolerates(): void
    {
        $this->assertSame(
            SchemaAction::Tolerate,
            OnDuplicate::Upsert->resolveSchemaAction(
                exists: true,
                sourceUpdatedAt: '2026-04-23T09:00:00.000+00:00',
                destUpdatedAt: '2026-04-23T10:00:00.000+00:00',
            ),
        );
    }

    public function testUpsertEqualTimestampsTolerates(): void
    {
        // Strict > comparison: equal means dest is already in sync, skip the
        // drop. Avoids unnecessary destructive rebuild when the user hasn't
        // touched source since the last migration.
        $stamp = '2026-04-23T10:00:00.000+00:00';
        $this->assertSame(
            SchemaAction::Tolerate,
            OnDuplicate::Upsert->resolveSchemaAction(
                exists: true,
                sourceUpdatedAt: $stamp,
                destUpdatedAt: $stamp,
            ),
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function unparseableTimestampPairs(): array
    {
        return [
            'both empty'        => ['', ''],
            'source empty'      => ['', '2026-04-23T10:00:00.000+00:00'],
            'dest empty'        => ['2026-04-23T10:00:00.000+00:00', ''],
            'source zero-date'  => ['0000-00-00 00:00:00', '2026-04-23T10:00:00.000+00:00'],
            'dest zero-date'    => ['2026-04-23T10:00:00.000+00:00', '0000-00-00 00:00:00'],
            'source garbage'    => ['not-a-date', '2026-04-23T10:00:00.000+00:00'],
            'dest garbage'      => ['2026-04-23T10:00:00.000+00:00', 'not-a-date'],
        ];
    }

    #[DataProvider('unparseableTimestampPairs')]
    public function testUpsertUnparseableTimestampsTolerate(string $source, string $dest): void
    {
        // Conservative: unparseable timestamps preserve existing destination
        // rather than risk a destructive drop on garbage input. Any
        // non-Appwrite source that emits malformed dates gets handled safely.
        $this->assertSame(
            SchemaAction::Tolerate,
            OnDuplicate::Upsert->resolveSchemaAction(
                exists: true,
                sourceUpdatedAt: $source,
                destUpdatedAt: $dest,
            ),
        );
    }

    public function testValuesListsAllCasesInDeclarationOrder(): void
    {
        // The values() helper is consumed by API/SDK param validators; this
        // tests protects against an accidental case-rename or reorder.
        $this->assertSame(['fail', 'skip', 'upsert'], OnDuplicate::values());
    }
}
