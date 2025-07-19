<?php

namespace Migration\Unit\General;

use PHPUnit\Framework\TestCase;
use Utopia\Migration\Sources\CSV;

class CSVTest extends TestCase
{
    private const RESOURCES_DIR = __DIR__ . '/../../resources/csv/';

    /**
     * @throws \ReflectionException
     */
    private function detectDelimiter($stream): string
    {
        $reflection = new \ReflectionClass(CSV::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $refMethod = $reflection->getMethod('delimiter');

        /** @noinspection PhpExpressionResultUnusedInspection */
        $refMethod->setAccessible(true);

        return $refMethod->invoke($instance, $stream);
    }

    public function testDetectDelimiter()
    {
        $cases = [
            ['file' => 'comma.csv',         'expected' => ','],
            ['file' => 'single_column.csv', 'expected' => ','], // fallback
            ['file' => 'empty.csv',         'expected' => ','], // fallback
            ['file' => 'quoted_fields.csv', 'expected' => ','],
            ['file' => 'semicolon.csv',     'expected' => ';'],
            ['file' => 'tab.csv',           'expected' => "\t"],
            ['file' => 'pipe.csv',          'expected' => '|'],
        ];

        foreach ($cases as $case) {
            $filepath = self::RESOURCES_DIR . $case['file'];
            $stream = fopen($filepath, 'r');
            $delimiter = $this->detectDelimiter($stream);
            fclose($stream);

            $this->assertEquals($case['expected'], $delimiter, "Failed for {$case['file']}");
        }
    }
}
