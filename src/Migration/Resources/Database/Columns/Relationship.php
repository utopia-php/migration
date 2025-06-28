<?php

namespace Utopia\Migration\Resources\Database\Columns;

use Utopia\Database\Database;
use Utopia\Migration\Resources\Database\Column;
use Utopia\Migration\Resources\Database\Table;

class Relationship extends Column
{
    public function __construct(
        string  $key,
        Table   $table,
        string  $relatedTable,
        string  $relationType,
        bool    $twoWay = false,
        ?string $twoWayKey = null,
        string  $onDelete = Database::RELATION_MUTATE_RESTRICT,
        string  $side = Database::RELATION_SIDE_PARENT,
        string  $createdAt = '',
        string  $updatedAt = ''
    ) {
        parent::__construct(
            $key,
            $table,
            options: [
                'relatedTable' => $relatedTable,
                'relationType' => $relationType,
                'twoWay' => $twoWay,
                'twoWayKey' => $twoWayKey,
                'onDelete' => $onDelete,
                'side' => $side,
            ],
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );
    }

    /**
     * @param array{
     *     key: string,
     *     collection: array{
     *         database: array{
     *             id: string,
     *             name: string,
     *         },
     *         name: string,
     *         id: string,
     *         documentSecurity: bool,
     *         permissions: ?array<string>
     *     },
     *     options: array{
     *         relatedCollection: string,
     *         relationType: string,
     *         twoWay: bool,
     *         twoWayKey: ?string,
     *         onDelete: string,
     *         side: string,
     *     },
     *     createdAt: string,
     *     updatedAt: string,
     * } $array
     * @return self
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['key'],
            Table::fromArray($array['collection']),
            relatedTable: $array['options']['relatedCollection'],
            relationType: $array['options']['relationType'],
            twoWay: $array['options']['twoWay'],
            twoWayKey: $array['options']['twoWayKey'],
            onDelete: $array['options']['onDelete'],
            side: $array['options']['side'],
            createdAt: $array['createdAt'] ?? '',
            updatedAt: $array['updatedAt'] ?? '',
        );
    }

    public function getType(): string
    {
        return Column::TYPE_RELATIONSHIP;
    }

    public function getRelatedTable(): string
    {
        return $this->options['relatedCollection'];
    }

    public function getRelationType(): string
    {
        return $this->options['relationType'];
    }

    public function getTwoWay(): bool
    {
        return $this->options['twoWay'];
    }

    public function getTwoWayKey(): ?string
    {
        return $this->options['twoWayKey'];
    }

    public function getOnDelete(): string
    {
        return $this->options['onDelete'];
    }

    public function getSide(): string
    {
        return $this->options['side'];
    }
}
