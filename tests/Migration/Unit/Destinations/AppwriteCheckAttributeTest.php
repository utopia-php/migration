<?php

namespace Utopia\Tests\Unit\Destinations;

use PHPUnit\Framework\TestCase;
use Utopia\Database\Attribute as UtopiaAttribute;
use Utopia\Database\Document as UtopiaDocument;
use Utopia\Query\Schema\ColumnType;

class AppwriteCheckAttributeTest extends TestCase
{
    public function testCheckAttributeUsesAttributeKeyNotMetadataId(): void
    {
        $source = (string) \file_get_contents(\dirname(__DIR__, 4) . '/src/Migration/Destinations/Appwrite.php');

        $this->assertStringContainsString(
            "checkAttribute(\$table, UtopiaAttribute::fromArray([",
            $source,
        );
        $this->assertStringContainsString(
            "'key' => \$resource->getKey(),",
            $source,
        );
        $this->assertStringNotContainsString(
            'checkAttribute($table, $column)',
            $source,
        );

        $column = new UtopiaDocument([
            '$id' => '1_2_title',
            'key' => 'title',
            'type' => 'string',
            'size' => 128,
            'required' => true,
            'signed' => true,
            'default' => null,
            'array' => false,
            'format' => '',
            'formatOptions' => [],
            'filters' => [],
            'options' => [],
        ]);

        $fromDocumentCopy = UtopiaAttribute::fromArray($column->getArrayCopy());
        $this->assertSame('1_2_title', $fromDocumentCopy->key);

        $attribute = UtopiaAttribute::fromArray([
            'key' => 'title',
            'type' => 'string',
            'size' => 128,
            'required' => true,
            'signed' => true,
            'default' => null,
            'array' => false,
            'format' => null,
            'formatOptions' => [],
            'filters' => [],
            'options' => null,
        ]);

        $this->assertInstanceOf(UtopiaAttribute::class, $attribute);
        $this->assertSame('title', $attribute->key);
        $this->assertSame(ColumnType::String, $attribute->type);
        $this->assertSame(128, $attribute->size);
    }
}
