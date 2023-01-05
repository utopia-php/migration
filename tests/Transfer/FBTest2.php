<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Utopia\CLI\Console;
use Utopia\Transfer\Transfer;
use Utopia\Transfer\Sources\Firebase;

$source = new Firebase(
    json_decode(file_get_contents(__DIR__ . '/../../test-service-account.json'), true),
    Firebase::AUTH_SERVICEACCOUNT
);
Console::log('Attempting Authentication...');


Console::log('Detecting Projects...');
var_dump($source->getProjects());


Console::log('Grabbing Users...');
var_dump($source->exportUsers());