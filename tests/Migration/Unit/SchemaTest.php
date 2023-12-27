<?php

use PHPUnit\Framework\TestCase;
use Utopia\Migration\Extends\Email;
use Utopia\Migration\Schemas\Schema;
use Utopia\Validator\Numeric;
use Utopia\Validator\Text;

class SchemaTest extends TestCase
{
    public function testValidData()
    {
        $schema = new Schema();
        $schema->add('test', [
            'name' => new Text(100),
            'age' => new Numeric(),
        ]);

        $result = $schema->validate(['name' => 'John', 'age' => 30], 'test');
        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function testMissingKey()
    {
        $schema = new Schema();
        $schema->add('test', [
            'name' => new Text(100),
            'age' => new Numeric(),
        ]);

        $result = $schema->validate(['name' => 'John'], 'test');

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
        $this->assertEquals('Field is missing', $result->getErrors()['age']);
    }

    public function testNestedValidation()
    {
        $schema = new Schema();
        $schema->add('test', [
            'name' => new Text(100),
            'profile' => [
                'age' => new Numeric(),
                'email' => new Email(),
            ],
        ]);

        $result = $schema->validate(['name' => 'John', 'profile' => ['age' => 30, 'email' => 'john@example.com']], 'test');
        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function testDoubleNestedValidators()
    {
        $schema = new Schema();
        $schema->add('test', [
            'name' => new Text(100),
            'profile' => [
                'age' => new Numeric(),
                'email' => new Email(),
                'provider' => [
                    'name' => new Text(100)
                ]
            ],
        ]);

        $result = $schema->validate([
            'name' => 'John', 
            'profile' => [
                'age' => 30, 'email' => 'test@utopia.com', 
                'provider' => [
                    'name' => 'Utopia'
                ]
            ]
        ], 'test');

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }
}
