<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Utopia\CLI\Console;
use Utopia\Transfer\Destinations\Appwrite;
use Utopia\Transfer\Resource;
use Utopia\Transfer\Sources\Firebase;
use Utopia\Transfer\Transfer;

$transfer = new Transfer(new Firebase(
    json_decode(file_get_contents(__DIR__ . '/../../test-service-account.json'), true),
    Firebase::AUTH_SERVICEACCOUNT
),
    new Appwrite('63b6499ba93d62a9488c', 'http://localhost/v1', '986ed5168e34ea75953192e3165abfa10792ac1ae7be6f562312f3bc282e628a80637d3a6041d4af35cf50d27f31a27013d6066ab32c3612278d97064c4b88b9cffadec0d5a4e53a6d0692cb5591d8ad9269cd2ef8ec9ce0d72f6b0c689ad172fb228c7ea7834e5db6d1deb42b4310e3b4a5791ef2a0078dbeed1b76c3cbfbdd'));

$transfer->run([
    Transfer::RESOURCE_USERS
], function($data) {
    Console::log('Got More Data!');
});