<?php

namespace Utopia\Tests\E2E\Sources\Live\Appwrite;

use Utopia\App;
use Utopia\Migration\Sources\Appwrite;
use Utopia\Tests\E2E\Sources\Base as SourcesBase;

class Base extends SourcesBase
{
    public function setUp(): void
    {
        $this->source = new Appwrite(
            App::getEnv('TEST_APPWRITE_PROJECT'), 
            App::getEnv('TEST_APPWRITE_ENDPOINT'), 
            App::getEnv( 'TEST_APPWRITE_KEY')
        );
        parent::setUp();
    }
}