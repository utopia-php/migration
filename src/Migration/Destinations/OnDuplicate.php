<?php

namespace Utopia\Migration\Destinations;

/**
 * Outcome of {@see OnDuplicate::resolveSchemaAction()}. Declared alongside
 * OnDuplicate because the two are designed together — any code that uses
 * SchemaAction necessarily imports OnDuplicate (as the source of the
 * decision), so the shared file satisfies autoloading in practice.
 */
enum SchemaAction
{
    case Create;
    case Tolerate;
    case DropAndRecreate;
    case UpdateInPlace;
}

enum OnDuplicate: string
{
    case Fail = 'fail';
    case Skip = 'skip';
    case Upsert = 'upsert';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return \array_values(\array_map(fn (self $case) => $case->value, self::cases()));
    }

    /**
     * Schema-level reconciliation decision.
     *
     * $canDrop = true  → leaves (attributes, indexes) get DropAndRecreate on Upsert-newer.
     * $canDrop = false → containers (databases, tables) get UpdateInPlace on Upsert-newer.
     * Default is false so destructive reconciliation requires explicit opt-in.
     */
    public function resolveSchemaAction(
        bool $exists,
        ?string $sourceUpdatedAt = null,
        ?string $destUpdatedAt = null,
        bool $canDrop = false,
    ): SchemaAction {
        if (!$exists) {
            return SchemaAction::Create;
        }
        return match ($this) {
            self::Fail   => SchemaAction::Create,
            self::Skip   => SchemaAction::Tolerate,
            self::Upsert => $this->sourceIsNewer($sourceUpdatedAt, $destUpdatedAt)
                ? ($canDrop ? SchemaAction::DropAndRecreate : SchemaAction::UpdateInPlace)
                : SchemaAction::Tolerate,
        };
    }

    /**
     * strtotime() accepts '0000-00-00' leniently (returns a large negative
     * epoch, not false), so reject non-positive epochs too. Null/empty are
     * treated as unknown → tolerate rather than risk a destructive drop.
     */
    private function sourceIsNewer(?string $source, ?string $dest): bool
    {
        if ($source === null || $source === '' || $dest === null || $dest === '') {
            return false;
        }
        $src = \strtotime($source);
        $dst = \strtotime($dest);
        if ($src === false || $dst === false || $src <= 0 || $dst <= 0) {
            return false;
        }
        return $src > $dst;
    }
}
