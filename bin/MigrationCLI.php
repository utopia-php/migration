<?php

require_once __DIR__.'/.././vendor/autoload.php';

use Dotenv\Dotenv;
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

    protected $source;

    protected $destination;

    /**
     * Prints the current status of migrations as a table after wiping the screen
     */
    public function drawFrame()
    {
        echo chr(27).chr(91).'H'.chr(27).chr(91).'J';

        $statusCounters = $this->transfer->getStatusCounters();

        $mask = "| %15.15s | %-7.7s | %10.10s | %7.7s | %7.7s | %8.8s |\n";
        printf($mask, 'Resource', 'Pending', 'Processing', 'Skipped', 'Warning', 'Error', 'Success');
        printf($mask, '-------------', '-------------', '-------------', '-------------', '-------------', '-------------', '-------------');
        foreach ($statusCounters as $resource => $data) {
            printf($mask, $resource, $data['pending'], $data['processing'], $data['skip'], $data['warning'], $data['error'], $data['success']);
        }

        // Render Errors
        $destErrors = $this->destination->getErrors();
        if (! empty($destErrors)) {
            echo "\n\nDestination Errors:\n";
            foreach ($destErrors as $error) {
                /** @var Utopia\Migration\Exception $error */
                echo $error->getResourceName().'['.$error->getResourceId().'] - '.$error->getMessage()."\n";
            }
        }

        $sourceErrors = $this->source->getErrors();
        if (! empty($sourceErrors)) {
            echo "\n\nSource Errors:\n";
            foreach ($sourceErrors as $error) {
                /** @var Utopia\Migration\Exception $error */
                echo $error->getResourceGroup().'['.$error->getResourceId().'] - '.$error->getMessage()."\n";
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
                    json_decode(file_get_contents(__DIR__.'/serviceAccount.json'), true)
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
                    $_ENV['DESTINATION_APPWRITE_TEST_KEY']
                );
            case 'local':
                return new Local('./localBackup');
            default:
                throw new Exception('Invalid destination provider');
        }
    }

    public function start()
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
        $this->transfer->run(
            $this->source->getSupportedResources(),
            function (array $resources) {
                $this->drawFrame();
            }
        );
    }
}

$instance = new MigrationCLI();
$instance->start();
$instance->drawFrame();
