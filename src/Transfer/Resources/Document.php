<?php

namespace Utopia\Transfer\Resources;

use Utopia\Transfer\Resource;

class Document extends Resource {
    protected string $id;
    protected string $database;
    protected Collection $collection;
    protected array $data;

    public function __construct(string $id, string $database, Collection $collection, array $data = [])
    {
        $this->id = $id;
        $this->database = $database;
        $this->collection = $collection;
        $this->data = $data;
    }

    public function getName(): string
    {
        return 'document';
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getDatabase(): string
    {
        return $this->database;
    }

    public function setDatabase(string $database): self
    {
        $this->database = $database;
        return $this;
    }

    public function getCollection(): Collection
    {
        return $this->collection;
    }

    public function setCollection(Collection $collection): self
    {
        $this->collection = $collection;
        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Set Data
     * 
     * @param array<string, mixed> $data
     * 
     * @return self
     */
    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function asArray(): array
    {
        return [
            'id' => $this->id,
            'database' => $this->database,
            'collection' => $this->collection,
            'attributes' => $this->data,
        ];
    }
}