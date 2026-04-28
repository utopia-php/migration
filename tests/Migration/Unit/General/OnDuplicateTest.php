<?php

namespace Migration\Unit\General;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Utopia\Migration\Destinations\OnDuplicate;
use Utopia\Migration\Destinations\SchemaAction;

/**
 * OnDuplicate::resolveSchemaAction is the load-bearing decision point for
 * re-migration tolerance. The destination then runs a spec-match guard that
 * overrides UpdateInPlace to Tolerate when source and dest already have
 * identical spec — see DestinationAppwrite. These tests lock the
 * mode × existence × updatedAt matrix.
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
        // Skip must ignore updatedAt entirely — it's the "don't touch" contract.
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

    public function testUpsertSourceNewerUpdatesInPlace(): void
    {
        // Source updatedAt strictly newer than dest → UpdateInPlace. The
        // caller (DestinationAppwrite) follows up with attribute/index spec
        // checks and falls through to drop+recreate when the SDK can't
        // express the change.
        $this->assertSame(
            SchemaAction::UpdateInPlace,
            OnDuplicate::Upsert->resolveSchemaAction(
                exists: true,
                sourceUpdatedAt: '2026-04-23T10:00:00.000+00:00',
                destUpdatedAt: '2026-04-23T09:59:59.000+00:00',
            ),
        );
    }

    public function testUpsertSourceEqualTolerates(): void
    {
        $when = '2026-04-23T10:00:00.000+00:00';
        $this->assertSame(
            SchemaAction::Tolerate,
            OnDuplicate::Upsert->resolveSchemaAction(
                exists: true,
                sourceUpdatedAt: $when,
                destUpdatedAt: $when,
            ),
        );
    }

    public function testUpsertSourceOlderTolerates(): void
    {
        // Dest is ahead — don't roll back. Avoids overwriting newer
        // destination edits with stale source data.
        $this->assertSame(
            SchemaAction::Tolerate,
            OnDuplicate::Upsert->resolveSchemaAction(
                exists: true,
                sourceUpdatedAt: '2026-04-23T09:00:00.000+00:00',
                destUpdatedAt: '2026-04-23T10:00:00.000+00:00',
            ),
        );
    }

    public function testUpsertNoTimestampsTolerates(): void
    {
        // No timestamps provided at all → no information to act on. Conservative:
        // Tolerate rather than risk a destructive update on uncertain input.
        $this->assertSame(
            SchemaAction::Tolerate,
            OnDuplicate::Upsert->resolveSchemaAction(exists: true),
        );
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function unparseableTimestampPairs(): array
    {
        return [
            'both empty'        => ['', ''],
            'both null'         => [null, null],
            'source empty'      => ['', '2026-04-23T10:00:00.000+00:00'],
            'dest empty'        => ['2026-04-23T10:00:00.000+00:00', ''],
            'source null'       => [null, '2026-04-23T10:00:00.000+00:00'],
            'dest null'         => ['2026-04-23T10:00:00.000+00:00', null],
            'source zero-date'  => ['0000-00-00 00:00:00', '2026-04-23T10:00:00.000+00:00'],
            'dest zero-date'    => ['2026-04-23T10:00:00.000+00:00', '0000-00-00 00:00:00'],
            'source garbage'    => ['not-a-date', '2026-04-23T10:00:00.000+00:00'],
            'dest garbage'      => ['2026-04-23T10:00:00.000+00:00', 'not-a-date'],
        ];
    }

    #[DataProvider('unparseableTimestampPairs')]
    public function testUpsertUnparseableUpdatedAtTolerates(?string $source, ?string $dest): void
    {
        // Conservative: unparseable updatedAt preserves existing destination
        // rather than risk a destructive update on garbage input.
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
        // protects against an accidental case-rename or reorder.
        $this->assertSame(['fail', 'skip', 'upsert'], OnDuplicate::values());
    }
}
