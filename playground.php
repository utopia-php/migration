<?php

/**
 * Playground for Transfer Library Tests
 *
 * A place to test and debug the Transfer Library stuff
 */
require_once __DIR__.'/vendor/autoload.php';

use Dotenv\Dotenv;
use Utopia\Transfer\Destinations\Appwrite as AppwriteDestination;
use Utopia\Transfer\Destinations\Local;
use Utopia\Transfer\Sources\Appwrite;
use Utopia\Transfer\Sources\Firebase;
use Utopia\Transfer\Sources\NHost;
use Utopia\Transfer\Sources\Supabase;
use Utopia\Transfer\Transfer;

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
    $sourceFirebase,
    $destinationLocal
);

/**
 * Run Transfer
 */
$transfer->run(
    [Transfer::GROUP_STORAGE_RESOURCES, Transfer::GROUP_DATABASES_RESOURCES],
    function (array $resources) {
    }
);
