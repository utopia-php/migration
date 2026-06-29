<?php

namespace Utopia\Tests\Unit\General;

use PHPUnit\Framework\TestCase;
use Utopia\Migration\Destinations\Appwrite;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Row;
use Utopia\Migration\Resources\Database\Table;
use Utopia\Migration\Resources\Functions\Deployment;
use Utopia\Migration\Resources\Functions\EnvVar;
use Utopia\Migration\Resources\Functions\Func;
use Utopia\Migration\Resources\Sites\Deployment as SiteDeployment;
use Utopia\Migration\Resources\Sites\EnvVar as SiteEnvVar;
use Utopia\Migration\Resources\Sites\Site;
use Utopia\Migration\Resources\Storage\Bucket;
use Utopia\Migration\Resources\Storage\File;
use Utopia\Migration\Transfer;
use Utopia\Tests\Unit\Adapters\MockDestination;
use Utopia\Tests\Unit\Adapters\MockSource;

class TransferTest extends TestCase
{
    protected Transfer $transfer;
    protected MockSource $source;
    protected MockDestination $destination;

    public function setup(): void
    {
        $this->source = new MockSource();
        $this->destination = new MockDestination();

        $this->transfer = new Transfer(
            $this->source,
            $this->destination
        );
    }

    /**
     * @throws \Exception
     */
    public function testRootResourceId(): void
    {
        /**
         * TEST FOR FAILURE
         * Make sure we can't create a transfer with multiple root resources when supplying a rootResourceId
         */
        try {
            $this->transfer->run([Resource::TYPE_USER, Resource::TYPE_DATABASE], function () {
            }, 'rootResourceId');
            $this->fail('Multiple root resources should not be allowed');
        } catch (\Exception $e) {
            $this->assertSame('Resource type must be set when resource ID is set.', $e->getMessage());
        }

        $this->source->pushMockResource(new Database('test', 'test'));
        $this->source->pushMockResource(new Database('test2', 'test'));

        /**
         * TEST FOR SUCCESS
         */
        $this->transfer->run(
            [Resource::TYPE_DATABASE],
            function () {
            },
            'test',
            Resource::TYPE_DATABASE
        );
        $this->assertCount(1, $this->destination->getResourceTypeData(Transfer::GROUP_DATABASES, Resource::TYPE_DATABASE));

        $database = $this->destination->getResourceById(Transfer::GROUP_DATABASES, Resource::TYPE_DATABASE, 'test');
        /** @var Database $database */
        $this->assertNotNull($database);
        $this->assertSame('test', $database->getDatabaseName());
        $this->assertSame('test', $database->getId());
    }

    /**
     * Row and document counts are aggregated into the cache by status. When such
     * a count exists for a resource type that was not part of the migration
     * request, getStatusCounters() must ignore it, exactly as it already does for
     * non-row resources via the isset() guard. Otherwise it reads an unseeded
     * 'pending' key (triggering an "Undefined array key" warning) and reports a
     * phantom, non-empty counter for a type the caller never asked to migrate.
     */
    public function testStatusCountersIgnoreUnrequestedRowCounts(): void
    {
        // No resource types were requested, so 'row'/'document' are unrequested.
        // A row count still leaks into the cache: the destination tallies row and
        // document counts by status as it imports them.
        $table = new Table(new Database('db', 'db'), 'table', 'table');
        $row = new Row('row-1', $table);
        $row->setStatus(Resource::STATUS_SUCCESS);

        $this->transfer->getCache()->add($row);

        $counters = $this->transfer->getStatusCounters();

        $this->assertArrayNotHasKey(Resource::TYPE_ROW, $counters);
        $this->assertSame([], $counters);
    }

    public function testAppwriteDestinationSkipsFunctionChildrenWhenParentFailed(): void
    {
        $destination = $this->createAppwriteDestinationWithoutConstructor();
        $function = new Func('function-1', 'Function 1', 'node-25');
        $function->setStatus(Resource::STATUS_ERROR, 'Invalid Runtime: node-25');

        $variable = new EnvVar('variable-1', $function, 'KEY', 'value');
        $deployment = new Deployment('deployment-1', $function, 1, 'index.js');

        $variable = $destination->importFunctionResource($variable);
        $deployment = $destination->importFunctionResource($deployment);

        $this->assertSame(Resource::STATUS_SKIPPED, $variable->getStatus());
        $this->assertSame('Parent function "function-1" failed to import', $variable->getMessage());
        $this->assertSame(Resource::STATUS_SKIPPED, $deployment->getStatus());
        $this->assertSame('Parent function "function-1" failed to import', $deployment->getMessage());
    }

    public function testAppwriteDestinationSkipsSiteChildrenWhenParentFailed(): void
    {
        $destination = $this->createAppwriteDestinationWithoutConstructor();
        $site = new Site('site-1', 'Site 1', 'other', 'node-25');
        $site->setStatus(Resource::STATUS_ERROR, 'Invalid Build Runtime: node-25');

        $variable = new SiteEnvVar('variable-1', $site, 'KEY', 'value');
        $deployment = new SiteDeployment('deployment-1', $site, 1);

        $variable = $destination->importSiteResource($variable);
        $deployment = $destination->importSiteResource($deployment);

        $this->assertSame(Resource::STATUS_SKIPPED, $variable->getStatus());
        $this->assertSame('Parent site "site-1" failed to import', $variable->getMessage());
        $this->assertSame(Resource::STATUS_SKIPPED, $deployment->getStatus());
        $this->assertSame('Parent site "site-1" failed to import', $deployment->getMessage());
    }

    public function testAppwriteDestinationSkipsFilesWhenBucketFailed(): void
    {
        $destination = $this->createAppwriteDestinationWithoutConstructor();
        $bucket = new Bucket('bucket-1', 'Bucket 1');
        $bucket->setStatus(Resource::STATUS_ERROR, 'Bucket already exists');

        $file = $destination->importFileResource(new File('file-1', $bucket, 'file.txt'));

        $this->assertSame(Resource::STATUS_SKIPPED, $file->getStatus());
        $this->assertSame('Parent bucket "bucket-1" failed to import', $file->getMessage());
    }

    public function testAppwriteDestinationImportsFunctionVariablesWhenParentWasSkipped(): void
    {
        $destination = $this->createAppwriteDestinationWithoutConstructor();
        $functions = new class (new \Appwrite\Client()) extends \Appwrite\Services\Functions {
            public string $functionId = '';

            public function createVariable(string $functionId, string $variableId, string $key, string $value, ?bool $secret = null): \Appwrite\Models\Variable
            {
                $this->functionId = $functionId;

                return new \Appwrite\Models\Variable($variableId, '', '', $key, $value, false, 'function', $functionId);
            }
        };

        $this->setAppwriteDestinationProperty($destination, 'functions', $functions);

        $function = new Func('function-1', 'Function 1', 'node-25');
        $function->setStatus(Resource::STATUS_SKIPPED, 'Already exists on destination');

        $variable = $destination->importFunctionResource(new EnvVar('variable-1', $function, 'KEY', 'value'));

        $this->assertSame(Resource::STATUS_SUCCESS, $variable->getStatus());
        $this->assertSame('function-1', $functions->functionId);
    }

    public function testAppwriteDestinationImportsSiteVariablesWhenParentWasSkipped(): void
    {
        $destination = $this->createAppwriteDestinationWithoutConstructor();
        $sites = new class (new \Appwrite\Client()) extends \Appwrite\Services\Sites {
            public string $siteId = '';

            public function createVariable(string $siteId, string $variableId, string $key, string $value, ?bool $secret = null): \Appwrite\Models\Variable
            {
                $this->siteId = $siteId;

                return new \Appwrite\Models\Variable($variableId, '', '', $key, $value, false, 'site', $siteId);
            }
        };

        $this->setAppwriteDestinationProperty($destination, 'sites', $sites);

        $site = new Site('site-1', 'Site 1', 'other', 'node-25');
        $site->setStatus(Resource::STATUS_SKIPPED, 'Already exists on destination');

        $variable = $destination->importSiteResource(new SiteEnvVar('variable-1', $site, 'KEY', 'value'));

        $this->assertSame(Resource::STATUS_SUCCESS, $variable->getStatus());
        $this->assertSame('site-1', $sites->siteId);
    }

    public function testAppwriteDestinationSupportsNode25FunctionRuntime(): void
    {
        $destination = $this->createAppwriteDestinationWithoutConstructor();
        $functions = new class (new \Appwrite\Client()) extends \Appwrite\Services\Functions {
            public string $runtime = '';

            public function create(string $functionId, string $name, \Appwrite\Enums\Runtime $runtime, ?array $execute = null, ?array $events = null, ?string $schedule = null, ?int $timeout = null, ?bool $enabled = null, ?bool $logging = null, ?string $entrypoint = null, ?string $commands = null, ?array $scopes = null, ?string $installationId = null, ?string $providerRepositoryId = null, ?string $providerBranch = null, ?bool $providerSilentMode = null, ?string $providerRootDirectory = null, ?array $providerBranches = null, ?array $providerPaths = null, ?string $buildSpecification = null, ?string $runtimeSpecification = null, ?int $deploymentRetention = null): \Appwrite\Models\FunctionModel
            {
                $this->runtime = $runtime->jsonSerialize();

                return new \Appwrite\Models\FunctionModel(
                    'function-1',
                    '',
                    '',
                    [],
                    'Function 1',
                    true,
                    false,
                    true,
                    'node-25',
                    0,
                    '',
                    '',
                    '',
                    '',
                    '',
                    [],
                    [],
                    [],
                    '',
                    0,
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    false,
                    [],
                    [],
                    '',
                    ''
                );
            }
        };

        $this->setAppwriteDestinationProperty($destination, 'functions', $functions);

        $function = $destination->importFunctionResource(new Func('function-1', 'Function 1', 'node-25'));

        $this->assertSame(Resource::STATUS_SUCCESS, $function->getStatus());
        $this->assertSame('node-25', $functions->runtime);
    }

    public function testAppwriteDestinationSupportsNode25SiteBuildRuntime(): void
    {
        $destination = $this->createAppwriteDestinationWithoutConstructor();
        $sites = new class (new \Appwrite\Client()) extends \Appwrite\Services\Sites {
            public string $buildRuntime = '';

            public function create(string $siteId, string $name, \Appwrite\Enums\Framework $framework, \Appwrite\Enums\BuildRuntime $buildRuntime, ?bool $enabled = null, ?bool $logging = null, ?int $timeout = null, ?string $installCommand = null, ?string $buildCommand = null, ?string $startCommand = null, ?string $outputDirectory = null, ?\Appwrite\Enums\Adapter $adapter = null, ?string $installationId = null, ?string $fallbackFile = null, ?string $providerRepositoryId = null, ?string $providerBranch = null, ?bool $providerSilentMode = null, ?string $providerRootDirectory = null, ?array $providerBranches = null, ?array $providerPaths = null, ?string $buildSpecification = null, ?string $runtimeSpecification = null, ?int $deploymentRetention = null): \Appwrite\Models\Site
            {
                $this->buildRuntime = $buildRuntime->jsonSerialize();

                return new \Appwrite\Models\Site(
                    'site-1',
                    '',
                    '',
                    'Site 1',
                    true,
                    false,
                    true,
                    'other',
                    0,
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    [],
                    0,
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    false,
                    [],
                    [],
                    '',
                    '',
                    'node-25',
                    'static',
                    ''
                );
            }
        };

        $this->setAppwriteDestinationProperty($destination, 'sites', $sites);

        $site = $destination->importSiteResource(new Site('site-1', 'Site 1', 'other', 'node-25'));

        $this->assertSame(Resource::STATUS_SUCCESS, $site->getStatus());
        $this->assertSame('node-25', $sites->buildRuntime);
    }

    public function testAppwriteDestinationKeepsInvalidFunctionRuntimeError(): void
    {
        $destination = $this->createAppwriteDestinationWithoutConstructor();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid Runtime: node-999');

        $destination->importFunctionResource(new Func('function-1', 'Function 1', 'node-999'));
    }

    public function testAppwriteDestinationKeepsInvalidSiteBuildRuntimeError(): void
    {
        $destination = $this->createAppwriteDestinationWithoutConstructor();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid Build Runtime: node-999');

        $destination->importSiteResource(new Site('site-1', 'Site 1', 'other', 'node-999'));
    }

    private function createAppwriteDestinationWithoutConstructor(): Appwrite
    {
        /** @var Appwrite $destination */
        $destination = (new \ReflectionClass(Appwrite::class))->newInstanceWithoutConstructor();

        return $destination;
    }

    private function setAppwriteDestinationProperty(Appwrite $destination, string $name, mixed $value): void
    {
        $property = new \ReflectionProperty(Appwrite::class, $name);
        $property->setValue($destination, $value);
    }
}
