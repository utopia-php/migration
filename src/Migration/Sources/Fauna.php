<?php

namespace Utopia\Migration\Sources;

use Utopia\Migration\Exception;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Attributes\Boolean;
use Utopia\Migration\Resources\Database\Attributes\DateTime;
use Utopia\Migration\Resources\Database\Attributes\Decimal;
use Utopia\Migration\Resources\Database\Attributes\Integer;
use Utopia\Migration\Resources\Database\Attributes\Text;
use Utopia\Migration\Resources\Database\Collection;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Document;
use Utopia\Migration\Source;
use Utopia\Migration\Transfer;

class Fauna extends Source
{
    protected string $secret;
    protected string $endpoint;

    public function __construct(string $secret, string $endpoint = 'https://db.fauna.com')
    {
        $this->secret = $secret;
        $this->endpoint = $endpoint;
        $this->headers['Authorization'] = 'Bearer ' . $this->secret;
        $this->headers['Content-Type'] = 'application/json';
    }

    public static function getName(): string
    {
        return 'Fauna';
    }

    /**
     * @return array<string>
     */
    public static function getSupportedResources(): array
    {
        return [
            // Databases
            Resource::TYPE_DATABASE,
            Resource::TYPE_COLLECTION,
            Resource::TYPE_DOCUMENT,
        ];
    }

    /**
     * @param array<string> $resources
     * @return array<string, int>
     * @throws \Exception
     */
    public function report(array $resources = []): array
    {
        $report = [];
        if (empty($resources)) {
            $resources = $this->getSupportedResources();
        }

        if (\in_array(Resource::TYPE_DATABASE, $resources)) {
            $report[Resource::TYPE_DATABASE] = 1; // Fauna has a single database per key
        }

        if (\in_array(Resource::TYPE_COLLECTION, $resources)) {
            try {
                $collections = $this->listCollections();
                $report[Resource::TYPE_COLLECTION] = count($collections);
            } catch (\Exception $e) {
                var_dump($e);
                $report[Resource::TYPE_COLLECTION] = 0;
            }
        }

        if (\in_array(Resource::TYPE_DOCUMENT, $resources)) {
            try {
                $collections = $this->listCollections();
                $report[Resource::TYPE_DOCUMENT] = 0;
                foreach ($collections as $collection) {
                    if (empty($collection['name'])) {
                        continue;
                    }
                    $documents = $this->listDocuments($collection['name']);
                    $report[Resource::TYPE_DOCUMENT] += count($documents);
                }
            } catch (\Exception $e) {
                var_dump($e);
                $report[Resource::TYPE_DOCUMENT] = 0;
            }
        }

        $this->previousReport = $report;

        return $report;
    }

    /**
     * Export Auth Group
     *
     * @param int $batchSize
     * @param array<string> $resources Resources to export
     */
    protected function exportGroupAuth(int $batchSize, array $resources): void
    {
        // Fauna doesn't have native user support
        return;
    }

    /**
     * Export Databases Group
     *
     * @param int $batchSize
     * @param array<string> $resources Resources to export
     */
    protected function exportGroupDatabases(int $batchSize, array $resources): void
    {
        try {
            if (\in_array(Resource::TYPE_DATABASE, $resources)) {
                $database = new Database('fauna', 'fauna');
                $this->callback([$database]);
            }

            if (\in_array(Resource::TYPE_COLLECTION, $resources)) {
                $this->exportCollections($batchSize);
            }

            if (\in_array(Resource::TYPE_DOCUMENT, $resources)) {
                $this->exportDocuments($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(new Exception(
                Resource::TYPE_DATABASE,
                Transfer::GROUP_DATABASES,
                message: $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            ));
        }
    }

    /**
     * Export Storage Group
     *
     * @param int $batchSize Max 5
     * @param array<string> $resources Resources to export
     */
    protected function exportGroupStorage(int $batchSize, array $resources): void
    {
        throw new \Exception('Storage migration not supported for Fauna');
    }

    /**
     * Export Functions Group
     *
     * @param int $batchSize
     * @param array<string> $resources Resources to export
     */
    protected function exportGroupFunctions(int $batchSize, array $resources): void
    {
        throw new \Exception('Functions migration not supported for Fauna');
    }

    private function exportCollections(int $batchSize): void
    {
        try {
            $collections = $this->listCollections();
            $database = new Database('fauna', 'fauna');
            
            $collectionResources = [];
            foreach ($collections as $collection) {
                $collectionResource = new Collection(
                    $database,
                    $collection['name'],
                    $collection['name']
                );
                $collectionResources[] = $collectionResource;

                // Infer schema from first 100 documents
                $documents = $this->listDocuments($collection['name'], 100);
                $schema = $this->inferSchema($documents);
                
                $attributes = [];
                foreach ($schema as $key => $type) {
                    $attributes[] = $this->createAttribute($key, $type, $collectionResource);
                }
                
                if (!empty($attributes)) {
                    $this->callback($attributes);
                }
            }
            
            if (!empty($collectionResources)) {
                $this->callback($collectionResources);
            }
        } catch (\Exception $e) {
            throw new Exception(
                Resource::TYPE_COLLECTION,
                Transfer::GROUP_DATABASES,
                message: $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            );
        }
    }

    private function exportDocuments(int $batchSize): void
    {
        try {
            $collections = $this->listCollections();
            $database = new Database('fauna', 'fauna');
            
            foreach ($collections as $collection) {
                $collectionResource = new Collection(
                    $database,
                    $collection['name'],
                    $collection['name']
                );
                
                $offset = 0;
                while (true) {
                    $documents = $this->listDocuments($collection['name'], $batchSize, $offset);
                    if (empty($documents)) {
                        break;
                    }
                    
                    $documentResources = [];
                    foreach ($documents as $document) {
                        $documentResources[] = new Document(
                            $document['ref']['id'],
                            $collectionResource,
                            $document['data']
                        );
                    }
                    
                    if (!empty($documentResources)) {
                        $this->callback($documentResources);
                    }
                    
                    $offset += $batchSize;
                }
            }
        } catch (\Exception $e) {
            throw new Exception(
                Resource::TYPE_DOCUMENT,
                Transfer::GROUP_DATABASES,
                message: $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            );
        }
    }

    private function listCollections(): array
    {
        $response = $this->call('POST', $this->endpoint . '/query/1', [], [
            'query' => 'Collection.all()',
        ]);

        // Check for the nested data structure
        if (!isset($response['data']['data']) || !is_array($response['data']['data'])) {
            return [];
        }

        return array_map(function ($collection) {
            if (!isset($collection['name']) || !is_string($collection['name'])) {
                return null;
            }

            $fields = [];
            if (isset($collection['fields']) && is_array($collection['fields'])) {
                foreach ($collection['fields'] as $fieldName => $field) {
                    $fields[$fieldName] = [
                        'type' => $field['signature'] ?? 'String',
                        'required' => false // Fauna doesn't specify required in schema
                    ];
                }
            }

            return [
                'name' => $collection['name'],
                'ref' => [
                    'id' => $collection['name']
                ],
                'fields' => $fields,
                'indexes' => $collection['indexes'] ?? [],
                'constraints' => $collection['constraints'] ?? []
            ];
        }, $response['data']['data']);
    }

    private function listDocuments(string $collectionName, int $limit = 0, int $offset = 0): array
    {
        $query = $collectionName . '.all()';
        
        if ($limit > 0) {
            $query = 'Take(' . $limit . ', Drop(' . $offset . ', ' . $collectionName . '.all()))';
        }

        $response = $this->call('POST', $this->endpoint . '/query/1', [], [
            'query' => $query,
        ]);

        // Check for the nested data structure
        if (!isset($response['data']['data']) || !is_array($response['data']['data'])) {
            return [];
        }

        return array_map(function ($document) {
            $data = [];
            
            // Extract all fields except special ones
            foreach ($document as $key => $value) {
                // Skip special Fauna fields
                if (in_array($key, ['id', 'coll', 'ts', 'ref'])) {
                    continue;
                }
                
                // Handle null values
                if ($value === null) {
                    $data[$key] = null;
                    continue;
                }
                
                // Handle objects (like address)
                if (is_array($value) && isset($value['coll'])) {
                    // This is a reference to another collection
                    $data[$key] = $value['id'];
                } elseif (is_array($value)) {
                    // This is a nested object
                    $data[$key] = $value;
                } else {
                    // Regular value
                    $data[$key] = $value;
                }
            }

            return [
                'ref' => [
                    'id' => $document['id']
                ],
                'data' => $data
            ];
        }, $response['data']['data']);
    }

    private function inferSchema(array $documents): array
    {
        $schema = [];
        
        // If the first document has schema information, use it
        if (!empty($documents) && isset($documents[0]['collection']['fields'])) {
            foreach ($documents[0]['collection']['fields'] as $key => $field) {
                $schema[$key] = $this->mapFaunaType($field['signature'] ?? 'String');
            }
            return $schema;
        }

        // Fallback to inferring from document data if schema not available
        foreach ($documents as $document) {
            if (!isset($document['data']) || !is_array($document['data'])) {
                continue;
            }
            foreach ($document['data'] as $key => $value) {
                if (!isset($schema[$key])) {
                    $schema[$key] = $this->getValueType($value);
                }
            }
        }
        return $schema;
    }

    private function mapFaunaType(string $faunaType): string
    {
        // Handle basic types
        if (str_contains($faunaType, 'String')) {
            return 'string';
        }
        if (str_contains($faunaType, 'Int') || str_contains($faunaType, 'Number')) {
            return 'integer';
        }
        if (str_contains($faunaType, 'Double') || str_contains($faunaType, 'Float')) {
            return 'float';
        }
        if (str_contains($faunaType, 'Boolean')) {
            return 'boolean';
        }
        if (str_contains($faunaType, 'Time')) {
            return 'datetime';
        }
        
        // For complex types (objects, arrays, etc), default to string
        return 'string';
    }

    private function getValueType($value): string
    {
        if (is_string($value)) {
            if ($this->isDateTimeString($value)) {
                return 'datetime';
            }
            return 'string';
        } elseif (is_int($value)) {
            return 'integer';
        } elseif (is_float($value)) {
            return 'float';
        } elseif (is_bool($value)) {
            return 'boolean';
        } else {
            return 'string'; // Default to string for complex types
        }
    }

    private function isDateTimeString(string $value): bool
    {
        return (bool) strtotime($value);
    }

    private function createAttribute(string $key, string $type, Collection $collection): Attribute
    {
        switch ($type) {
            case 'string':
                return new Text(
                    $key,
                    $collection,
                    required: false,
                    default: null,
                    array: false,
                    size: 1000000
                );
            case 'integer':
                return new Integer(
                    $key,
                    $collection,
                    required: false,
                    default: null,
                    array: false
                );
            case 'float':
                return new Decimal(
                    $key,
                    $collection,
                    required: false,
                    default: null,
                    array: false
                );
            case 'boolean':
                return new Boolean(
                    $key,
                    $collection,
                    required: false,
                    default: null,
                    array: false
                );
            case 'datetime':
                return new DateTime(
                    $key,
                    $collection,
                    required: false,
                    default: null,
                    array: false
                );
            default:
                return new Text(
                    $key,
                    $collection,
                    required: false,
                    default: null,
                    array: false,
                    size: 1000000
                );
        }
    }
} 