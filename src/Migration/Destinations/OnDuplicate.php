<?php

namespace Utopia\Migration\Destinations;

/**
 * Behavior when a destination row with an existing ID is encountered.
 */
enum OnDuplicate: string
{
    case Fail = 'fail';
    case Skip = 'skip';
    case Upsert = 'upsert';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return \array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * Skip and Upsert tolerate an existing schema resource on re-migration;
     * Fail rethrows. Callers gate their pre-check on this before issuing a
     * `getDocument` against the destination metadata — on Fail mode the
     * library's own `DuplicateException` surfaces from the create call below.
     */
    public function toleratesSchemaDuplicate(): bool
    {
        return $this !== self::Fail;
    }

    /**
     * Upsert reconciliation trigger: true iff mode is Upsert AND source's
     * updatedAt is strictly newer than destination's. Skip and Fail always
     * return false (they don't reconcile). Unparseable timestamps → false
     * (conservative: preserve the existing destination rather than risk a
     * destructive drop on garbage input).
     */
    public function shouldReconcileSchema(string $sourceUpdatedAt, string $destUpdatedAt): bool
    {
        if ($this !== self::Upsert) {
            return false;
        }
        $src = \strtotime($sourceUpdatedAt);
        $dst = \strtotime($destUpdatedAt);
        if ($src === false || $dst === false) {
            return false;
        }
        return $src > $dst;
    }
}
