<?php

namespace Migration\Unit\General;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Utopia\Migration\Destinations\OnDuplicate;
use Utopia\Migration\Destinations\SchemaAction;

/**
 * OnDuplicate::resolveSchemaAction is the load-bearing decision point for
 * re-migration tolerance. These tests lock the mode × existence × createdAt ×
 * updatedAt × canDrop matrix so a future refactor can't silently shift the
 * mapping.
 *
 * Decision rules under Upsert (with $exists = true):
 *   - createdAt differs → resource was deleted+recreated on source. Caller can
 *     drop and recreate (canDrop=true) or update in place (canDrop=false; for
 *     containers like databases/tables which would lose children on drop).
 *   - createdAt same + updatedAt newer → metadata-only edit. UpdateInPlace.
 *     The caller checks whether changed fields are SDK-updatable; if not it
 *     falls through to DropAndRecreate.
 *   - createdAt same + updatedAt equal/older → Tolerate.
 *   - Unparseable createdAt or updatedAt → conservative; treat as if no diff.
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
        // Skip must ignore createdAt/updatedAt entirely — it's the "don't
        // touch" contract. Exercise both source-newer and dest-newer to prove
        // comparisons aren't consulted.
        $this->assertSame(
            SchemaAction::Tolerate,
            OnDuplicate::Skip->resolveSchemaAction(
                exists: true,
                sourceCreatedAt: '2020-01-01T00:00:00.000+00:00',
                destCreatedAt: '2026-01-01T00:00:00.000+00:00',
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

    public function testUpsertCreatedAtDiffersOnLeafDropsAndRecreates(): void
    {
        // canDrop: true + createdAt differs → leaf was deleted/recreated on
        // source. Drop the destination's incarnation and create fresh.
        $this->assertSame(
            SchemaAction::DropAndRecreate,
            OnDuplicate::Upsert->resolveSchemaAction(
                exists: true,
                sourceCreatedAt: '2026-04-23T10:00:00.000+00:00',
                destCreatedAt: '2026-04-20T10:00:00.000+00:00',
                canDrop: true,
            ),
        );
    }

    public function testUpsertCreatedAtDiffersOnContainerUpdatesInPlace(): void
    {
        // Default canDrop: false (containers like databases/tables). Even on
        // createdAt diff, drop is forbidden — children would be lost. Fall
        // back to UpdateInPlace.
        $this->assertSame(
            SchemaAction::UpdateInPlace,
            OnDuplicate::Upsert->resolveSchemaAction(
                exists: true,
                sourceCreatedAt: '2026-04-23T10:00:00.000+00:00',
                destCreatedAt: '2026-04-20T10:00:00.000+00:00',
            ),
        );
    }

    public function testUpsertCreatedAtSameUpdatedAtNewerUpdatesInPlace(): void
    {
        // Same createdAt + newer updatedAt → metadata-only edit on the same
        // physical resource. UpdateInPlace; caller checks whether the changed
        // fields are SDK-updatable and falls back to DropAndRecreate if not.
        $this->assertSame(
            SchemaAction::UpdateInPlace,
            OnDuplicate::Upsert->resolveSchemaAction(
                exists: true,
                sourceCreatedAt: '2026-04-20T10:00:00.000+00:00',
                destCreatedAt: '2026-04-20T10:00:00.000+00:00',
                sourceUpdatedAt: '2026-04-23T10:00:00.000+00:00',
                destUpdatedAt: '2026-04-23T09:59:59.000+00:00',
                canDrop: true,
            ),
        );
    }

    public function testUpsertCreatedAtSameUpdatedAtEqualTolerates(): void
    {
        // Same createdAt + same updatedAt → nothing changed. Skip the work.
        $created = '2026-04-20T10:00:00.000+00:00';
        $updated = '2026-04-23T10:00:00.000+00:00';
        $this->assertSame(
            SchemaAction::Tolerate,
            OnDuplicate::Upsert->resolveSchemaAction(
                exists: true,
                sourceCreatedAt: $created,
                destCreatedAt: $created,
                sourceUpdatedAt: $updated,
                destUpdatedAt: $updated,
                canDrop: true,
            ),
        );
    }

    public function testUpsertCreatedAtSameUpdatedAtOlderTolerates(): void
    {
        // Dest is ahead — don't roll back. Avoids overwriting newer
        // destination edits with stale source data.
        $this->assertSame(
            SchemaAction::Tolerate,
            OnDuplicate::Upsert->resolveSchemaAction(
                exists: true,
                sourceCreatedAt: '2026-04-20T10:00:00.000+00:00',
                destCreatedAt: '2026-04-20T10:00:00.000+00:00',
                sourceUpdatedAt: '2026-04-23T09:00:00.000+00:00',
                destUpdatedAt: '2026-04-23T10:00:00.000+00:00',
                canDrop: true,
            ),
        );
    }

    public function testUpsertNoTimestampsTolerates(): void
    {
        // No timestamps provided at all → no information to act on. Conservative:
        // Tolerate rather than risk destructive action on uncertain input.
        $this->assertSame(
            SchemaAction::Tolerate,
            OnDuplicate::Upsert->resolveSchemaAction(
                exists: true,
                canDrop: true,
            ),
        );
    }

    public function testUpsertOnlyUpdatedAtNewerWithoutCreatedAtUpdatesInPlace(): void
    {
        // CreatedAt missing on either side (e.g. older Appwrite versions, CSV
        // sources): can't detect "recreated", so the only signal is updatedAt.
        // Newer source → UpdateInPlace path (caller validates SDK-updatability).
        $this->assertSame(
            SchemaAction::UpdateInPlace,
            OnDuplicate::Upsert->resolveSchemaAction(
                exists: true,
                sourceUpdatedAt: '2026-04-23T10:00:00.000+00:00',
                destUpdatedAt: '2026-04-23T09:59:59.000+00:00',
                canDrop: true,
            ),
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
    public function testUpsertUnparseableCreatedAtDoesNotTriggerDrop(?string $source, ?string $dest): void
    {
        // Unparseable createdAt → don't trigger DropAndRecreate. With
        // updatedAt also absent, falls through to Tolerate. Safety net for
        // sources that emit malformed timestamps.
        $this->assertSame(
            SchemaAction::Tolerate,
            OnDuplicate::Upsert->resolveSchemaAction(
                exists: true,
                sourceCreatedAt: $source,
                destCreatedAt: $dest,
                canDrop: true,
            ),
        );
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
