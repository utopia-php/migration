<?php

namespace Utopia\Migration\Destinations;

/**
 * Caller branches on one of these three outcomes after asking
 * {@see OnDuplicate::resolveSchemaAction()} what to do with a possibly
 * pre-existing schema resource on the destination.
 */
enum SchemaAction
{
    /** Resource doesn't exist — run the normal create flow. */
    case Create;

    /** Resource exists; leave it alone (Skip, or Upsert with dest up-to-date). */
    case Tolerate;

    /** Resource exists; Upsert mode + source strictly newer — drop + recreate. */
    case DropAndRecreate;
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
     * Only safe to call for leaf resources (attributes, indexes) that can be
     * dropped to reconcile. Containers whose data must be preserved
     * (databases, tables) should use the simpler "tolerate existing" inline
     * check instead — this method's DropAndRecreate outcome would destroy
     * their user data.
     */
    public function resolveSchemaAction(
        bool $exists,
        string $sourceUpdatedAt = '',
        string $destUpdatedAt = '',
    ): SchemaAction {
        if (!$exists) {
            return SchemaAction::Create;
        }
        return match ($this) {
            self::Fail   => SchemaAction::Create,   // caller's create flow will throw
            self::Skip   => SchemaAction::Tolerate,
            self::Upsert => $this->sourceIsNewer($sourceUpdatedAt, $destUpdatedAt)
                ? SchemaAction::DropAndRecreate
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
