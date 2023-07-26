<?php

/**
 * Playground for Transfer Library Tests
 *
 * A place to test and debug the Transfer Library stuff
 */
require_once __DIR__.'/vendor/autoload.php';

use Appwrite\Query;
use Dotenv\Dotenv;
use Utopia\Transfer\Destinations\Appwrite as AppwriteDestination;
use Utopia\Transfer\Destinations\Local;
use Utopia\Transfer\Resource;
use Utopia\Transfer\Sources\Appwrite;
use Utopia\Transfer\Sources\Firebase;
use Utopia\Transfer\Sources\NHost;
use Utopia\Transfer\Sources\Supabase;
use Utopia\Transfer\Transfer;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// cleanupAppwrite();

/**
 * Initialise All Source Adapters
 */
$sourceAppwrite = new Appwrite(
    $_ENV['SOURCE_APPWRITE_TEST_PROJECT'],
    $_ENV['SOURCE_APPWRITE_TEST_ENDPOINT'],
    $_ENV['SOURCE_APPWRITE_TEST_KEY']
);

$firebase = json_decode($_ENV['FIREBASE_TEST_ACCOUNT'], true);

$sourceFirebase = new Firebase(
    $firebase,
    $firebase['project_id'] ?? '',
);

$sourceNHost = new NHost(
    $_ENV['NHOST_TEST_SUBDOMAIN'] ?? '',
    $_ENV['NHOST_TEST_REGION'] ?? '',
    $_ENV['NHOST_TEST_SECRET'] ?? '',
    $_ENV['NHOST_TEST_DATABASE'] ?? '',
    $_ENV['NHOST_TEST_USERNAME'] ?? '',
    $_ENV['NHOST_TEST_PASSWORD'] ?? '',
);

$sourceSupabase = new Supabase(
    $_ENV['SUPABASE_TEST_ENDPOINT'] ?? '',
    $_ENV['SUPABASE_TEST_KEY'] ?? '',
    $_ENV['SUPABASE_TEST_HOST'] ?? '',
    $_ENV['SUPABASE_TEST_DATABASE'] ?? '',
    $_ENV['SUPABASE_TEST_USERNAME'] ?? '',
    $_ENV['SUPABASE_TEST_PASSWORD'] ?? '',
);

/**
 * Initialise All Destination Adapters
 */
$destinationAppwrite = new AppwriteDestination(
    $_ENV['DESTINATION_APPWRITE_TEST_PROJECT'],
    $_ENV['DESTINATION_APPWRITE_TEST_ENDPOINT'],
    $_ENV['DESTINATION_APPWRITE_TEST_KEY']
);

$destinationLocal = new Local(__DIR__.'/localBackup/');

/**
 * Initialise Transfer Class
 */
$transfer = new Transfer(
    $sourceSupabase,
    $destinationLocal
);

$sourceSupabase->report();

/**
 * Run Transfer
 */
$transfer->run($sourceAppwrite->getSupportedResources(),
    function (array $resources) use ($transfer) {
        var_dump($transfer->getStatusCounters());
    }
);

// function cleanupAppwrite()
// {
//     $client = new \Appwrite\Client();

//     $client
//         ->setEndpoint($_ENV['DESTINATION_APPWRITE_TEST_ENDPOINT'])
//         ->setProject($_ENV['DESTINATION_APPWRITE_TEST_PROJECT'])
//         ->setKey($_ENV['DESTINATION_APPWRITE_TEST_KEY']);

//     $databaseService = new \Appwrite\Services\Databases($client);
//     $listDatabases = $databaseService->list();
//     foreach ($listDatabases['databases'] as $database) {
//         $databaseId = $database['$id'];
//         $listCollections = $databaseService->listCollections($databaseId);
//         foreach ($listCollections['collections'] as $collection) {
//             $collectionId = $collection['$id'];
//             $listDocuments = $databaseService->listDocuments($databaseId, $collectionId);
//             foreach ($listDocuments['documents'] as $document) {
//                 $documentId = $document['$id'];
//                 $databaseService->deleteDocument($databaseId, $collectionId, $documentId);
//             }
//         }

//         $databaseService->delete($databaseId);
//     }

//     $usersService = new \Appwrite\Services\Users($client);
//     $listUsers = $usersService->list();
//     if ($listUsers['total'] > count($listUsers['users'])) {
//         while ($listUsers['total'] > count($listUsers['users'])) {
//             $listUsers['users'] = array_merge($listUsers['users'], $usersService->list(
//                 [Query::cursorAfter(
//                     $listUsers['users'][count($listUsers['users']) - 1]['$id']
//                 )]
//             )['users']);
//         }
//     }

//     foreach ($listUsers['users'] as $user) {
//         $userId = $user['$id'];
//         $usersService->delete($userId);
//     }

//     $teamsService = new \Appwrite\Services\Teams($client);
//     $listTeams = $teamsService->list();
//     foreach ($listTeams['teams'] as $team) {
//         $teamId = $team['$id'];
//         $teamsService->delete($teamId);
//     }

//     $storageService = new \Appwrite\Services\Storage($client);
//     $listBuckets = $storageService->listBuckets();
//     foreach ($listBuckets['buckets'] as $bucket) {
//         $bucketId = $bucket['$id'];
//         $listFiles = $storageService->listFiles($bucketId);
//         foreach ($listFiles['files'] as $file) {
//             $fileId = $file['$id'];
//             $storageService->deleteFile($bucketId, $fileId);
//         }

//         $storageService->deleteBucket($bucketId);
//     }
// }

$report = [];

$cache = $transfer->getCache()->getAll();

foreach ($cache as $type => $resources) {
    foreach ($resources as $resource) {
        if ($resource->getStatus() !== Resource::STATUS_ERROR) {
            continue;
        }

        var_dump($resource);
    }
}
