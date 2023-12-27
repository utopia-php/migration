<?php

use Utopia\Migration\Extends\Email;
use Utopia\Migration\Resource;
use Utopia\Migration\Schemas\Schema;
use Utopia\Validator\Boolean;
use Utopia\Validator\Text;

$firebaseSchema = new Schema();

// Auth
$firebaseSchema->add(Resource::TYPE_USER, [
    'passwordHash' => new Text(1024),
    'displayName' => new Text(1024),
    'localId' => new Text(1024),
    'photoUrl' => new Text(1024),
    'customAttributes' => new Text(1024),
    'salt' => new Text(1024),
    'phoneNumber' => new Text(1024),
    'screenName' => new Text(1024),
    'createdAt' => new Text(1024),
    'disabled' => new Boolean(),
    'emailVerified' => new Boolean(),
    'email' => new Email(),
]);

// Storage
$firebaseSchema->add(Resource::TYPE_BUCKET, [
    'id' => new Text(1024),
    'name' => new Text(1024),
]);

$firebaseSchema->add(Resource::TYPE_FILE, [
    'name' => new Text(1024),
    'timeCreated' => new Text(1024),
    'updated' => new Text(1024),
]);

// Databases
$firebaseSchema->add(Resource::TYPE_DATABASE, [
    'name' => new Text(1024),
    'uid' => new Text(1024),
    'createTime' => new Text(1024),
    'updateTime' => new Text(1024),
]);