<?php

namespace Utopia\Migration\Sources;

use Utopia\CLI\Console;
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

    public Device $deviceForLocal;

    public function __construct(string $resourceId, string $filePath, Device $deviceForLocal)
    {
        $this->filePath = $filePath;
        $this->resourceId = $resourceId;
        $this->deviceForLocal = $deviceForLocal;
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
        } finally {
            // delete the temporary file!
            // temporary logs.
            Console::log('File exists: '.$this->deviceForLocal->exists($this->filePath));
            $this->deviceForLocal->delete($this->filePath);
            Console::log('File exists: '.$this->deviceForLocal->exists($this->filePath));
        }
    }

    private function exportDocuments(int $batchSize): void
    {
        if (! $this->deviceForLocal->exists($this->filePath)) {
            return;
        }

        $stream = fopen($this->filePath, 'r');
        if (! $stream) {
            return;
        }

        $headers = fgetcsv($stream);
        if (! is_array($headers) || count($headers) === 0) {
            fclose($stream);
            return;
        }

        [$databaseId, $collectionId] = explode(':', $this->resourceId);
        // TODO: @itznotabug, @jake - do we need to check for permissions here or db handles it?
        $collection = new Collection(new Database($databaseId, ''), '', $collectionId);

        $buffer = [];

        while (($row = fgetcsv($stream)) !== false) {
            $data = array_combine($headers, $row);
            if ($data === false) {
                continue;
            }

            $docId = $data['$id'] ?? 'unique()';
            $document = new Document($docId, $collection, $data);

            $buffer[] = $document;

            if (count($buffer) === $batchSize) {
                $this->callback($buffer);
                $buffer = [];
            }
        }

        fclose($stream);

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
