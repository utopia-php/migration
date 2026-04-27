<?php

namespace Utopia\Migration\Destinations;

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
     * $canDrop = false (containers like databases/tables) forces UpdateInPlace
     * even on createdAt-different — dropping would orphan children.
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

    private function createdAtDiffers(?string $source, ?string $dest): bool
    {
        $src = $this->parseTimestamp($source);
        $dst = $this->parseTimestamp($dest);
        return $src !== null && $dst !== null && $src !== $dst;
    }

    private function sourceIsNewer(?string $source, ?string $dest): bool
    {
        $src = $this->parseTimestamp($source);
        $dst = $this->parseTimestamp($dest);
        return $src !== null && $dst !== null && $src > $dst;
    }

    /**
     * strtotime accepts '0000-00-00' leniently (returns a large negative epoch,
     * not false), so non-positive epochs are rejected too.
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
