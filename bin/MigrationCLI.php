<?php

require_once __DIR__ . '/.././vendor/autoload.php';

use Appwrite\Query;
use Dotenv\Dotenv;
use Utopia\Cache\Adapter\None;
use Utopia\Cache\Cache;
use Utopia\Database\Adapter\MariaDB;
use Utopia\Database\Database;
use Utopia\Database\Document;
use Utopia\Database\Validator\Authorization;
use Utopia\Migration\Destination;
use Utopia\Migration\Destinations\Appwrite as DestinationsAppwrite;
use Utopia\Migration\Destinations\Local;
use Utopia\Migration\Source;
use Utopia\Migration\Sources\Appwrite;
use Utopia\Migration\Sources\Firebase;
use Utopia\Migration\Sources\NHost;
use Utopia\Migration\Sources\Supabase;
use Utopia\Migration\Transfer;

/**
 * Migrations CLI Tool
 */
class MigrationCLI
{
    protected Transfer $transfer;

    protected mixed $source;

    protected mixed $destination;

    protected const array STRUCTURE = [
        '$collection' => 'databases',
        '$id' => 'collections',
        'name' => 'Collections',
        'attributes' => [
            [
                '$id' => 'databaseInternalId',
                'type' => Database::VAR_STRING,
                'format' => '',
                'size' => Database::LENGTH_KEY,
                'signed' => true,
                'required' => true,
                'default' => null,
                'array' => false,
                'filters' => [],
            ],
            [
                '$id' => 'databaseId',
                'type' => Database::VAR_STRING,
                'signed' => true,
                'size' => Database::LENGTH_KEY,
                'format' => '',
                'filters' => [],
                'required' => true,
                'default' => null,
                'array' => false,
            ],
            [
                '$id' => 'name',
                'type' => Database::VAR_STRING,
                'size' => 256,
                'required' => true,
                'signed' => true,
                'array' => false,
                'filters' => [],
            ],
            [
                '$id' => 'enabled',
                'type' => Database::VAR_BOOLEAN,
                'signed' => true,
                'size' => 0,
                'format' => '',
                'filters' => [],
                'required' => true,
                'default' => null,
                'array' => false,
            ],
            [
                '$id' => 'documentSecurity',
                'type' => Database::VAR_BOOLEAN,
                'signed' => true,
                'size' => 0,
                'format' => '',
                'filters' => [],
                'required' => true,
                'default' => null,
                'array' => false,
            ],
            [
                '$id' => 'attributes',
                'type' => Database::VAR_STRING,
                'size' => 1000000,
                'required' => false,
                'signed' => true,
                'array' => false,
                'filters' => ['subQueryAttributes'],
            ],
            [
                '$id' => 'indexes',
                'type' => Database::VAR_STRING,
                'size' => 1000000,
                'required' => false,
                'signed' => true,
                'array' => false,
                'filters' => ['subQueryIndexes'],
            ],
            [
                '$id' => 'search',
                'type' => Database::VAR_STRING,
                'format' => '',
                'size' => 16384,
                'signed' => true,
                'required' => false,
                'default' => null,
                'array' => false,
                'filters' => [],
            ],
        ],
        'indexes' => [
            [
                '$id' => '_fulltext_search',
                'type' => Database::INDEX_FULLTEXT,
                'attributes' => ['search'],
                'lengths' => [],
                'orders' => [],
            ],
            [
                '$id' => '_key_name',
                'type' => Database::INDEX_KEY,
                'attributes' => ['name'],
                'lengths' => [256],
                'orders' => [Database::ORDER_ASC],
            ],
            [
                '$id' => '_key_enabled',
                'type' => Database::INDEX_KEY,
                'attributes' => ['enabled'],
                'lengths' => [],
                'orders' => [Database::ORDER_ASC],
            ],
            [
                '$id' => '_key_documentSecurity',
                'type' => Database::INDEX_KEY,
                'attributes' => ['documentSecurity'],
                'lengths' => [],
                'orders' => [Database::ORDER_ASC],
            ],
        ],
    ];

    /**
     * Prints the current status of migrations as a table after wiping the screen
     */
    public function drawFrame(): void
    {
        echo chr(27) . chr(91) . 'H' . chr(27) . chr(91) . 'J';

        $statusCounters = $this->transfer->getStatusCounters();

        $mask = "| %15.15s | %-7.7s | %10.10s | %7.7s | %7.7s | %8.8s | %8.8s |\n";
        printf($mask, 'Resource', 'Pending', 'Processing', 'Skipped', 'Warning', 'Error', 'Success');
        printf($mask, '-------------', '-------------', '-------------', '-------------', '-------------', '-------------', '-------------');
        foreach ($statusCounters as $resource => $data) {
            printf($mask, $resource, $data['pending'], $data['processing'], $data['skip'], $data['warning'], $data['error'], $data['success']);
        }

        // Render Errors
        $destErrors = $this->destination->getErrors();
        if (!empty($destErrors)) {
            echo "\n\nDestination Errors:\n";
            foreach ($destErrors as $error) {
                /** @var Utopia\Migration\Exception $error */
                echo $error->getResourceName() . '[' . $error->getResourceId() . '] - ' . $error->getMessage() . "\n";
            }
        }

        $sourceErrors = $this->source->getErrors();
        if (!empty($sourceErrors)) {
            echo "\n\nSource Errors:\n";
            foreach ($sourceErrors as $error) {
                /** @var Utopia\Migration\Exception $error */
                echo $error->getResourceGroup() . '[' . $error->getResourceId() . '] - ' . $error->getMessage() . "\n";
            }
        }

        // Render Warnings
        $sourceWarnings = $this->source->getWarnings();
        if (!empty($sourceWarnings)) {
            echo "\n\nSource Warnings:\n";
            foreach ($sourceWarnings as $warning) {
                /** @var Utopia\Migration\Warning $warning */
                echo $warning->getResourceName() . '[' . $warning->getResourceId() . '] - ' . $warning->getMessage() . "\n";
            }
        }

        $destWarnings = $this->destination->getWarnings();
        if (!empty($destWarnings)) {
            echo "\n\nDestination Warnings:\n";
            foreach ($destWarnings as $warning) {
                /** @var Utopia\Migration\Warning $warning */
                echo $warning->getResourceName() . '[' . $warning->getResourceId() . '] - ' . $warning->getMessage() . "\n";
            }
        }
    }

    public function getSource(): Source
    {
        switch ($_ENV['SOURCE_PROVIDER']) {
            case 'appwrite':
                return new Appwrite(
                    $_ENV['SOURCE_APPWRITE_TEST_PROJECT'],
                    $_ENV['SOURCE_APPWRITE_TEST_ENDPOINT'],
                    $_ENV['SOURCE_APPWRITE_TEST_KEY']
                );
            case 'supabase':
                return new Supabase(
                    $_ENV['SOURCE_SUPABASE_TEST_ENDPOINT'],
                    $_ENV['SOURCE_SUPABASE_TEST_KEY'],
                    $_ENV['SOURCE_SUPABASE_TEST_HOST'],
                    $_ENV['SOURCE_SUPABASE_TEST_DATBASE_NAME'],
                    $_ENV['SOURCE_SUPABASE_TEST_DATABASE_USER'],
                    $_ENV['SOURCE_SUPABASE_TEST_DATABASE_PASSWORD']
                );
            case 'firebase':
                return new Firebase(
                    json_decode(file_get_contents(__DIR__ . '/serviceAccount.json'), true)
                );
            case 'nhost':
                return new NHost(
                    $_ENV['SOURCE_NHOST_TEST_SUBDOMAIN'],
                    $_ENV['SOURCE_NHOST_TEST_REGION'],
                    $_ENV['SOURCE_NHOST_TEST_ADMIN_SECRET'],
                    $_ENV['SOURCE_NHOST_TEST_DATABASE_NAME'],
                    $_ENV['SOURCE_NHOST_TEST_DATABASE_USER'],
                    $_ENV['SOURCE_NHOST_TEST_DATABASE_PASSWORD']
                );
            default:
                throw new Exception('Invalid source provider');
        }
    }

    public function getDestination(): Destination
    {
        switch ($_ENV['DESTINATION_PROVIDER']) {
            case 'appwrite':
                return new DestinationsAppwrite(
                    $_ENV['DESTINATION_APPWRITE_TEST_PROJECT'],
                    $_ENV['DESTINATION_APPWRITE_TEST_ENDPOINT'],
                    $_ENV['DESTINATION_APPWRITE_TEST_KEY'],
                    $this->getDatabase(),
                    self::STRUCTURE
                );
            case 'local':
                return new Local('./localBackup');
            default:
                throw new Exception('Invalid destination provider');
        }
    }

    public function getDatabase(): Database
    {
        Database::addFilter(
            'subQueryAttributes',
            function (mixed $value) {
                return;
            },
            function (mixed $value, Document $document, Database $database) {
                $attributes = $database->find('attributes', [
                    Query::equal('collectionInternalId', [$document->getInternalId()]),
                    Query::equal('databaseInternalId', [$document->getAttribute('databaseInternalId')]),
                    Query::limit($database->getLimitForAttributes()),
                ]);

                foreach ($attributes as $attribute) {
                    if ($attribute->getAttribute('type') === Database::VAR_RELATIONSHIP) {
                        $options = $attribute->getAttribute('options');
                        foreach ($options as $key => $value) {
                            $attribute->setAttribute($key, $value);
                        }
                        $attribute->removeAttribute('options');
                    }
                }

                return $attributes;
            }
        );

        Database::addFilter(
            'subQueryIndexes',
            function (mixed $value) {
                return;
            },
            function (mixed $value, Document $document, Database $database) {
                return $database
                    ->find('indexes', [
                        Query::equal('collectionInternalId', [$document->getInternalId()]),
                        Query::equal('databaseInternalId', [$document->getAttribute('databaseInternalId')]),
                        Query::limit($database->getLimitForIndexes()),
                    ]);
            }
        );

        Database::addFilter(
            'casting',
            function (mixed $value) {
                return json_encode(['value' => $value], JSON_PRESERVE_ZERO_FRACTION);
            },
            function (mixed $value) {
                if (is_null($value)) {
                    return null;
                }

                return json_decode($value, true)['value'];
            }
        );

        Database::addFilter(
            'enum',
            function (mixed $value, Document $attribute) {
                if ($attribute->isSet('elements')) {
                    $attribute->removeAttribute('elements');
                }

                return $value;
            },
            function (mixed $value, Document $attribute) {
                $formatOptions = \json_decode($attribute->getAttribute('formatOptions', '[]'), true);
                if (isset($formatOptions['elements'])) {
                    $attribute->setAttribute('elements', $formatOptions['elements']);
                }

                return $value;
            }
        );

        Database::addFilter(
            'range',
            function (mixed $value, Document $attribute) {
                if ($attribute->isSet('min')) {
                    $attribute->removeAttribute('min');
                }
                if ($attribute->isSet('max')) {
                    $attribute->removeAttribute('max');
                }

                return $value;
            },
            function (mixed $value, Document $attribute) {
                $formatOptions = json_decode($attribute->getAttribute('formatOptions', '[]'), true);
                if (isset($formatOptions['min']) || isset($formatOptions['max'])) {
                    $attribute
                        ->setAttribute('min', $formatOptions['min'])
                        ->setAttribute('max', $formatOptions['max'])
                    ;
                }

                return $value;
            }
        );

        $database = new Database(
            new MariaDB(new PDO(
                $_ENV['DESTINATION_APPWRITE_TEST_DSN'],
                $_ENV['DESTINATION_APPWRITE_TEST_USER'],
                $_ENV['DESTINATION_APPWRITE_TEST_PASSWORD'],
                [
                    PDO::ATTR_TIMEOUT => 3,
                    PDO::ATTR_PERSISTENT => true,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => true,
                    PDO::ATTR_STRINGIFY_FETCHES => true
                ],
            )),
            new Cache(new None())
        );

        $database
            ->setDatabase('appwrite')
            ->setNamespace('_' . $_ENV['DESTINATION_APPWRITE_TEST_NAMESPACE']);

        return $database;
    }

    public function start(): void
    {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        /**
         * Initialise All Source Adapters
         */
        $this->source = $this->getSource();

        // $source->report();

        $this->destination = $this->getDestination();

        /**
         * Initialise Transfer Class
         */
        $this->transfer = new Transfer(
            $this->source,
            $this->destination
        );

        /**
         * Run Transfer
         */
        Authorization::skip(fn () => $this->transfer->run(
            [
                \Utopia\Migration\Resources\Database\Database::getName(),
                \Utopia\Migration\Resources\Database\Collection::getName(),
                \Utopia\Migration\Resources\Database\Attribute::getName(),
                \Utopia\Migration\Resources\Database\Index::getName(),
            ],
            function () {
                $this->drawFrame();
            }
        ));
    }
}

$instance = new MigrationCLI();
$instance->start();
$instance->drawFrame();
