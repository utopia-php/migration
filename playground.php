<?php

/**
 * Playground for Migration Library Tests
 *
 * A place to test and debug the Migration Library stuff
 */
require_once __DIR__.'/vendor/autoload.php';

use Dotenv\Dotenv;
use Utopia\Migration\Destinations\Appwrite as AppwriteDestination;
use Utopia\Migration\Destinations\Local;
use Utopia\Migration\Resource;
use Utopia\Migration\Sources\Appwrite;
use Utopia\Migration\Sources\Firebase;
use Utopia\Migration\Sources\NHost;
use Utopia\Migration\Sources\Supabase;
use Utopia\Migration\Transfer;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

/**
 * Initialise All Source Adapters
 */
$sourceAppwrite = new Appwrite(
    $_ENV['SOURCE_APPWRITE_TEST_PROJECT'],
    $_ENV['SOURCE_APPWRITE_TEST_ENDPOINT'],
    $_ENV['SOURCE_APPWRITE_TEST_KEY']
);

// $firebase = json_decode($_ENV['FIREBASE_TEST_ACCOUNT'], true);

// $sourceFirebase = new Firebase(
//     $firebase,
//     $firebase['project_id'] ?? '',
// );

// $sourceNHost = new NHost(
//     $_ENV['NHOST_TEST_SUBDOMAIN'] ?? '',
//     $_ENV['NHOST_TEST_REGION'] ?? '',
//     $_ENV['NHOST_TEST_SECRET'] ?? '',
//     $_ENV['NHOST_TEST_DATABASE'] ?? '',
//     $_ENV['NHOST_TEST_USERNAME'] ?? '',
//     $_ENV['NHOST_TEST_PASSWORD'] ?? '',
// );

// $sourceSupabase = new Supabase(
//     $_ENV['SUPABASE_TEST_ENDPOINT'] ?? '',
//     $_ENV['SUPABASE_TEST_KEY'] ?? '',
//     $_ENV['SUPABASE_TEST_HOST'] ?? '',
//     $_ENV['SUPABASE_TEST_DATABASE'] ?? '',
//     $_ENV['SUPABASE_TEST_USERNAME'] ?? '',
//     $_ENV['SUPABASE_TEST_PASSWORD'] ?? '',
// );

// /**
//  * Initialise All Destination Adapters
//  */
$destinationAppwrite = new AppwriteDestination(
    $_ENV['DESTINATION_APPWRITE_TEST_PROJECT'],
    $_ENV['DESTINATION_APPWRITE_TEST_ENDPOINT'],
    $_ENV['DESTINATION_APPWRITE_TEST_KEY']
);

$destinationLocal = new Local(__DIR__.'/localBackup/');

var_dump($sourceAppwrite->report());

/**
 * Initialise Transfer Class
 */
// $transfer = new Transfer(
//     $sourceAppwrite,
//     $destinationAppwrite
// );

// $sourceAppwrite->report();

// // /**
// //  * Run Transfer
// //  */
// $transfer->run($sourceAppwrite->getSupportedResources(),
//     function (array $resources) {
//     }
// );

// $report = [];

// $cache = $transfer->getCache()->getAll();

// foreach ($cache as $type => $resources) {
//     foreach ($resources as $resource) {
//         if ($resource->getStatus() !== Resource::STATUS_ERROR) {
//             continue;
//         }
//     }
// }
