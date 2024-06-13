<?php

require_once __DIR__.'/.././vendor/autoload.php';

use Dotenv\Dotenv;
use Utopia\Migration\Destinations\Appwrite as DestinationsAppwrite;
use Utopia\Migration\Sources\Appwrite;
use Utopia\Migration\Transfer;

/**
 * Migrations CLI Tool
 */
class MigrationCLI
{
    protected Transfer $transfer;

    protected Appwrite $source;

    protected DestinationsAppwrite $destination;

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
                echo $error->getResourceType().'['.$error->getResourceId().'] - '.$error->getMessage()."\n";
            }
        }
    }

    public function start()
    {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        /**
         * Initialise All Source Adapters
         */
        $this->source = new Appwrite(
            $_ENV['SOURCE_APPWRITE_TEST_PROJECT'],
            $_ENV['SOURCE_APPWRITE_TEST_ENDPOINT'],
            $_ENV['SOURCE_APPWRITE_TEST_KEY']
        );

        // $source->report();

        $this->destination = new DestinationsAppwrite(
            $_ENV['DESTINATION_APPWRITE_TEST_PROJECT'],
            $_ENV['DESTINATION_APPWRITE_TEST_ENDPOINT'],
            $_ENV['DESTINATION_APPWRITE_TEST_KEY']
        );

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
