<?php

namespace Utopia\Migration\Sources;

use Utopia\Migration\Exception;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Collection;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Document;
use Utopia\Migration\Resources\Storage\File;
use Utopia\Migration\Source;
use Utopia\Migration\Transfer;
use Utopia\Storage\Device;

class Csv extends Source
{
    public string $filePath;

    /**
     * format: `{databaseId:collectionId}`
     */
    public string $resourceId;

    public Device $deviceForFiles;

    public function __construct(string $resourceId, string $filePath, Device $deviceForFiles)
    {
        $this->$filePath = $filePath;
        $this->resourceId = $resourceId;
        $this->deviceForFiles = $deviceForFiles;
    }

    public static function getName(): string
    {
        return 'Csv';
    }

    public static function getSupportedResources(): array
    {
        return [
            Resource::TYPE_DOCUMENT,
        ];
    }

    public function report(array $resources = []): array
    {
        return [];
    }

    protected function exportGroupAuth(int $batchSize, array $resources): void
    {
        throw new \Exception('Not Implemented');
    }

    protected function exportGroupDatabases(int $batchSize, array $resources): void
    {
        try {
            if (\in_array(Resource::TYPE_DOCUMENT, $resources)) {
                $this->exportDocuments($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_DOCUMENT,
                    Transfer::GROUP_DATABASES,
                    message: $e->getMessage(),
                    code: $e->getCode(),
                    previous: $e
                )
            );
        }
    }

    private function exportDocuments(int $batchSize): void
    {
        if (! $this->deviceForFiles->exists($this->filePath)) {
            return;
        }

        [$databaseId, $collectionId] = explode(':', $this->resourceId);

        $csvFileSource = $this->deviceForFiles->read($this->filePath);

        $file = fopen($csvFileSource, 'r');
        if (! $file) {
            return;
        }

        $headers = fgetcsv($file);
        if (! is_array($headers)) {
            fclose($file);
            return;
        }

        $buffer = [];

        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($headers, $row);
            if ($data === false) {
                continue;
            }

            $docId = $data['$id'] ?? 'unique()';
            $database = new Database($databaseId, '');
            $collection = new Collection($database, '', $collectionId);
            $document = new Document($docId, $collection, $data);

            echo "CSV Row:\n";
            var_dump($data);

            echo "Document:\n";
            var_dump($document);

            $buffer[] = $document;

            if (count($buffer) === $batchSize) {
                $this->callback($buffer);
                $buffer = [];
            }
        }

        fclose($file);

        if (! empty($buffer)) {
            $this->callback($buffer);
        }
    }

    protected function exportGroupStorage(int $batchSize, array $resources): void
    {
        throw new \Exception('Not Implemented');
    }

    protected function exportBuckets(int $batchSize): void
    {
        throw new \Exception('Not Implemented');
    }

    private function exportFiles(int $batchSize): void
    {
        throw new \Exception('Not Implemented');
    }

    private function exportFile(File $file): void
    {
        throw new \Exception('Not Implemented');
    }

    protected function exportGroupFunctions(int $batchSize, array $resources): void
    {
        throw new \Exception('Not Implemented');
    }
}
