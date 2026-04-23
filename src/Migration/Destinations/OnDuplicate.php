<?php

namespace Utopia\Migration\Destinations;

/**
 * Caller branches on one of these outcomes after asking
 * {@see OnDuplicate::resolveSchemaAction()} what to do with a possibly
 * pre-existing schema resource on the destination.
 */
enum SchemaAction
{
    /** Resource doesn't exist — run the normal create flow. */
    case Create;

    /** Resource exists; leave it alone (Skip, or Upsert with dest up-to-date). */
    case Tolerate;

    /**
     * Resource exists; Upsert mode + source strictly newer + resource is a
     * leaf (attribute/index) — drop + recreate. Data-preserving containers
     * get {@see self::UpdateInPlace} instead.
     */
    case DropAndRecreate;

    /**
     * Resource exists; Upsert mode + source strictly newer + resource is a
     * container (database/table) — update metadata in place without
     * touching children. Callers should only overwrite fields that are
     * safe to source-wins (name, enabled, search, permissions, etc.) and
     * must never touch immutable fields ($id, $createdAt, internal
     * sequences).
     */
    case UpdateInPlace;
}

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
     * Single decision point for schema-level reconciliation on re-migration.
     *
     * Callers typically short-circuit on Fail mode before invoking this (to
     * avoid the destination metadata lookup entirely — the library's own
     * DuplicateException surfaces from the create call as designed).
     *
     * $canDrop picks the Upsert-newer reconciliation strategy; default
     * false is the safe option — destructive reconciliation requires
     * explicit opt-in at the call site:
     *   - true  → DropAndRecreate (attributes, indexes; column data is
     *             repopulated by the follow-up row Upsert, or is pure
     *             metadata for indexes).
     *   - false → UpdateInPlace (databases, tables; their child rows and
     *             sub-resources must be preserved, so destructive
     *             reconciliation is replaced with an updateDocument on the
     *             container's own metadata document).
     */
    public function resolveSchemaAction(
        bool $exists,
        string $sourceUpdatedAt = '',
        string $destUpdatedAt = '',
        bool $canDrop = false,
    ): SchemaAction {
        if (!$exists) {
            return SchemaAction::Create;
        }
        return match ($this) {
            self::Fail   => SchemaAction::Create,   // caller's create flow will throw
            self::Skip   => SchemaAction::Tolerate,
            self::Upsert => $this->sourceIsNewer($sourceUpdatedAt, $destUpdatedAt)
                ? ($canDrop ? SchemaAction::DropAndRecreate : SchemaAction::UpdateInPlace)
                : SchemaAction::Tolerate,
        };
    }

    /**
     * Unparseable or clearly-invalid timestamps → false (conservative:
     * preserve the existing destination rather than risk a destructive drop
     * on garbage input). PHP's strtotime() accepts some malformed dates
     * leniently — '0000-00-00 00:00:00' for example parses to a large
     * negative epoch rather than returning false — so we also reject
     * non-positive epochs. Any legitimate Appwrite updatedAt is well past
     * 1970-01-01.
     */
    private function sourceIsNewer(string $source, string $dest): bool
    {
        $src = \strtotime($source);
        $dst = \strtotime($dest);
        if ($src === false || $dst === false || $src <= 0 || $dst <= 0) {
            return false;
        }
        return $src > $dst;
    }
}
