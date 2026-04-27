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
     * createdAt-different → the source-side resource was deleted+recreated, so
     * it's a different physical incarnation. Destination should follow suit
     * with DropAndRecreate (or UpdateInPlace if $canDrop is false — containers).
     *
     * Same createdAt + newer updatedAt → metadata-only edit on the same
     * resource. UpdateInPlace; the caller checks whether the specific changed
     * fields are SDK-updatable and falls back to DropAndRecreate if not.
     *
     * $canDrop = true  → leaves (attributes, indexes) can be dropped.
     * $canDrop = false → containers (databases, tables) get UpdateInPlace
     *                    even on createdAt-different (drop would lose children).
     */
    public function resolveSchemaAction(
        bool $exists,
        ?string $sourceCreatedAt = null,
        ?string $destCreatedAt = null,
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
            self::Upsert => $this->resolveUpsertAction(
                $sourceCreatedAt,
                $destCreatedAt,
                $sourceUpdatedAt,
                $destUpdatedAt,
                $canDrop,
            ),
        };
    }

    private function resolveUpsertAction(
        ?string $sourceCreatedAt,
        ?string $destCreatedAt,
        ?string $sourceUpdatedAt,
        ?string $destUpdatedAt,
        bool $canDrop,
    ): SchemaAction {
        if ($this->createdAtDiffers($sourceCreatedAt, $destCreatedAt)) {
            return $canDrop ? SchemaAction::DropAndRecreate : SchemaAction::UpdateInPlace;
        }

        if ($this->sourceIsNewer($sourceUpdatedAt, $destUpdatedAt)) {
            return SchemaAction::UpdateInPlace;
        }

        return SchemaAction::Tolerate;
    }

    /**
     * Whether source and destination have different physical createdAt values.
     * Unknown (null/empty/zero-date) on either side → false.
     */
    private function createdAtDiffers(?string $source, ?string $dest): bool
    {
        $src = $this->parseTimestamp($source);
        $dst = $this->parseTimestamp($dest);
        return $src !== null && $dst !== null && $src !== $dst;
    }

    /**
     * Whether source's timestamp is strictly newer than destination's.
     * Unknown (null/empty/zero-date) on either side → false.
     */
    private function sourceIsNewer(?string $source, ?string $dest): bool
    {
        $src = $this->parseTimestamp($source);
        $dst = $this->parseTimestamp($dest);
        return $src !== null && $dst !== null && $src > $dst;
    }

    /**
     * strtotime accepts '0000-00-00' leniently (returns a large negative epoch,
     * not false), so non-positive epochs are rejected too. Null/empty/garbage
     * → null so callers treat the comparison as unknown.
     */
    private function parseTimestamp(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $epoch = \strtotime($value);
        if ($epoch === false || $epoch <= 0) {
            return null;
        }
        return $epoch;
    }
}
