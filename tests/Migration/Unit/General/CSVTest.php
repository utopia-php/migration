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

    /**
     * Test CSV getName static method
     */
    public function testGetName()
    {
        $this->assertEquals('CSV', CSV::getName());
    }

    /**
     * Test CSV getSupportedResources static method
     */
    public function testGetSupportedResources()
    {
        $supportedResources = CSV::getSupportedResources();
        $this->assertIsArray($supportedResources);
        $this->assertContains('row', $supportedResources);
    }

    /**
     * Test delimiter detection with edge cases and boundary conditions
     */
    public function testDetectDelimiterEdgeCases()
    {
        // Test with very large files
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_large_');
        $handle = fopen($tempFile, 'w');
        
        // Create a large CSV with comma delimiter
        for ($i = 0; $i < 1000; $i++) {
            fwrite($handle, "col1,col2,col3,col4\n");
        }
        fclose($handle);
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Failed to detect delimiter in large file');
    }

    /**
     * Test delimiter detection with mixed delimiters (should pick most common)
     */
    public function testDetectDelimiterMixedDelimiters()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_mixed_');
        file_put_contents($tempFile, "col1,col2,col3\nval1;val2;val3\nval4,val5,val6\nval7,val8,val9");
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Failed to detect most common delimiter');
    }

    /**
     * Test delimiter detection with quoted fields containing delimiters
     */
    public function testDetectDelimiterQuotedFieldsWithDelimiters()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_quoted_');
        file_put_contents($tempFile, '"col1,with,comma",col2,col3' . "\n" . '"val1;with;semicolon",val2,val3');
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Failed to handle quoted fields with delimiters');
    }

    /**
     * Test delimiter detection with unusual but valid delimiters
     */
    public function testDetectDelimiterUnusualDelimiters()
    {
        $testCases = [
            ['content' => "col1~col2~col3\nval1~val2~val3", 'expected' => '~'],
            ['content' => "col1^col2^col3\nval1^val2^val3", 'expected' => '^'],
            ['content' => "col1:col2:col3\nval1:val2:val3", 'expected' => ':'],
        ];

        foreach ($testCases as $case) {
            $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_unusual_');
            file_put_contents($tempFile, $case['content']);
            
            $stream = fopen($tempFile, 'r');
            $delimiter = $this->detectDelimiter($stream);
            fclose($stream);
            unlink($tempFile);
            
            $this->assertEquals($case['expected'], $delimiter, "Failed to detect unusual delimiter: {$case['expected']}");
        }
    }

    /**
     * Test delimiter detection with files containing only headers
     */
    public function testDetectDelimiterHeaderOnly()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_header_only_');
        file_put_contents($tempFile, "header1,header2,header3");
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Failed to detect delimiter in header-only file');
    }

    /**
     * Test delimiter detection with files containing special characters
     */
    public function testDetectDelimiterSpecialCharacters()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_special_');
        file_put_contents($tempFile, "col1,col2,col3\n\"val with \nnewline\",val2,val3\nval4,\"val with \"\"quotes\"\"\",val6");
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Failed to handle special characters in CSV');
    }

    /**
     * Test delimiter detection with extremely wide files (many columns)
     */
    public function testDetectDelimiterWideFile()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_wide_');
        
        // Create a file with 100 columns
        $headers = [];
        $values = [];
        for ($i = 1; $i <= 100; $i++) {
            $headers[] = "col{$i}";
            $values[] = "val{$i}";
        }
        
        $content = implode(',', $headers) . "\n" . implode(',', $values);
        file_put_contents($tempFile, $content);
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Failed to detect delimiter in wide file');
    }

    /**
     * Test delimiter detection with binary or non-UTF8 content
     */
    public function testDetectDelimiterNonUTF8()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_binary_');
        
        // Create content with some non-UTF8 characters but still CSV structure
        $content = "col1,col2,col3\n" . chr(200) . chr(201) . ",val2,val3";
        file_put_contents($tempFile, $content);
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Failed to handle non-UTF8 content');
    }

    /**
     * Test with file stream at different positions
     */
    public function testDetectDelimiterStreamPosition()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_position_');
        file_put_contents($tempFile, "header1,header2,header3\nval1,val2,val3\nval4,val5,val6");
        
        $stream = fopen($tempFile, 'r');
        
        // Move stream position and test
        fread($stream, 10); // Read some bytes
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Failed when stream not at beginning');
    }

    /**
     * Test delimiter detection with files having inconsistent row lengths
     */
    public function testDetectDelimiterInconsistentRows()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_inconsistent_');
        file_put_contents($tempFile, "col1,col2,col3\nval1,val2\nval3,val4,val5,val6\nval7,val8,val9");
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Failed with inconsistent row lengths');
    }

    /**
     * Test delimiter detection performance with various file sizes
     */
    public function testDetectDelimiterPerformance()
    {
        $fileSizes = [100, 1000, 10000];
        
        foreach ($fileSizes as $size) {
            $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_perf_');
            $handle = fopen($tempFile, 'w');
            
            for ($i = 0; $i < $size; $i++) {
                fwrite($handle, "col1,col2,col3,col4\n");
            }
            fclose($handle);
            
            $startTime = microtime(true);
            $stream = fopen($tempFile, 'r');
            $delimiter = $this->detectDelimiter($stream);
            fclose($stream);
            $endTime = microtime(true);
            
            unlink($tempFile);
            
            $this->assertEquals(',', $delimiter, "Failed performance test for size {$size}");
            $this->assertLessThan(1.0, $endTime - $startTime, "Performance test too slow for size {$size}");
        }
    }

    /**
     * Test with malformed CSV files
     */
    public function testDetectDelimiterMalformedCSV()
    {
        $malformedCases = [
            ['content' => 'col1,col2,col3"val1,val2,val3', 'expected' => ','], // Missing newline
            ['content' => '"unclosed quote,col2,col3', 'expected' => ','], // Unclosed quote
            ['content' => 'col1,,col3\nval1,,val3', 'expected' => ','], // Empty fields
        ];

        foreach ($malformedCases as $case) {
            $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_malformed_');
            file_put_contents($tempFile, $case['content']);
            
            $stream = fopen($tempFile, 'r');
            $delimiter = $this->detectDelimiter($stream);
            fclose($stream);
            unlink($tempFile);
            
            $this->assertEquals($case['expected'], $delimiter, 'Failed to handle malformed CSV');
        }
    }

    /**
     * Test delimiter detection with various encoding types
     */
    public function testDetectDelimiterDifferentEncodings()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_encoding_');
        
        // Test with UTF-8 BOM
        $content = "\xEF\xBB\xBF" . "col1,col2,col3\nval1,val2,val3";
        file_put_contents($tempFile, $content);
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Failed to handle UTF-8 BOM');
    }

    /**
     * Test error handling for invalid streams
     */
    public function testDetectDelimiterErrorHandling()
    {
        // Test with non-existent file
        $nonExistentFile = '/tmp/non_existent_file.csv';
        if (!file_exists($nonExistentFile)) {
            $stream = @fopen($nonExistentFile, 'r');
            $this->assertFalse($stream, 'Should return false for non-existent file');
        }
    }

    /**
     * Test delimiter detection with Unicode characters
     */
    public function testDetectDelimiterUnicode()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_unicode_');
        file_put_contents($tempFile, "名前,メール,年齢\nジョン,john@example.com,25\nジェーン,jane@example.com,30");
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Failed to handle Unicode characters');
    }

    /**
     * Test delimiter detection with different line endings
     */
    public function testDetectDelimiterDifferentLineEndings()
    {
        $lineEndingTests = [
            ['content' => "col1,col2,col3\rval1,val2,val3", 'name' => 'CR (\\r)'],
            ['content' => "col1,col2,col3\nval1,val2,val3", 'name' => 'LF (\\n)'],
            ['content' => "col1,col2,col3\r\nval1,val2,val3", 'name' => 'CRLF (\\r\\n)'],
        ];

        foreach ($lineEndingTests as $test) {
            $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_line_ending_');
            file_put_contents($tempFile, $test['content']);
            
            $stream = fopen($tempFile, 'r');
            $delimiter = $this->detectDelimiter($stream);
            fclose($stream);
            unlink($tempFile);
            
            $this->assertEquals(',', $delimiter, "Failed to handle {$test['name']} line endings");
        }
    }

    /**
     * Test delimiter detection with files containing only whitespace
     */
    public function testDetectDelimiterWhitespaceOnly()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_whitespace_');
        file_put_contents($tempFile, "   \n  \t  \n   ");
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Should fallback to comma for whitespace-only files');
    }

    /**
     * Test delimiter detection with extremely long lines
     */
    public function testDetectDelimiterLongLines()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_long_lines_');
        
        // Create a very long line with many fields
        $longLine = str_repeat('field,', 1000) . 'lastfield';
        file_put_contents($tempFile, $longLine);
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Failed to handle extremely long lines');
    }

    /**
     * Test delimiter detection with nested quotes
     */
    public function testDetectDelimiterNestedQuotes()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_nested_quotes_');
        file_put_contents($tempFile, '"outer ""inner"" quote",regular,field');
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Failed to handle nested quotes');
    }

    /**
     * Test delimiter detection consistency across multiple calls
     */
    public function testDetectDelimiterConsistency()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_consistency_');
        file_put_contents($tempFile, "col1,col2,col3\nval1,val2,val3");
        
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $stream = fopen($tempFile, 'r');
            $results[] = $this->detectDelimiter($stream);
            fclose($stream);
        }
        unlink($tempFile);
        
        $uniqueResults = array_unique($results);
        $this->assertCount(1, $uniqueResults, 'Delimiter detection should be consistent across multiple calls');
        $this->assertEquals(',', $results[0], 'Should consistently detect comma delimiter');
    }

    /**
     * Test delimiter detection with scoring algorithm edge cases
     */
    public function testDelimiterScoringAlgorithm()
    {
        // Test case where one delimiter appears more frequently but with poor consistency
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_scoring_');
        file_put_contents($tempFile, "a,b,c,d,e,f\nx;y\nz,w,v");
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        // Should prefer comma due to better consistency
        $this->assertEquals(',', $delimiter, 'Failed to properly score delimiter consistency');
    }

    /**
     * Test delimiter detection with no clear winner
     */
    public function testDelimiterDetectionNoWinner()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_no_winner_');
        file_put_contents($tempFile, "singlecolumnwithnodelimiters\nanotherrowwithnodelimiters");
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        // Should fallback to comma when no delimiter is clearly superior
        $this->assertEquals(',', $delimiter, 'Should fallback to comma when no delimiter wins');
    }

    /**
     * Test delimiter detection with quality scoring
     */
    public function testDelimiterQualityScoring()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_quality_');
        
        // Create file where semicolon creates more meaningful fields
        file_put_contents($tempFile, "name;email;age\nJohn Smith;john@example.com;25\nJane Doe;jane@example.com;30");
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(';', $delimiter, 'Should detect semicolon based on field quality');
    }

    /**
     * Test delimiter detection with short field content
     */
    public function testDelimiterDetectionShortFields()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_short_fields_');
        file_put_contents($tempFile, "a,b,c\nx,y,z\n1,2,3");
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Should handle short fields correctly');
    }

    /**
     * Test delimiter detection with coefficient of variation calculation
     */
    public function testDelimiterCoefficientOfVariation()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_cv_');
        
        // Create data with consistent comma usage vs inconsistent semicolon usage
        file_put_contents($tempFile, "a,b,c\nd,e,f\ng;h;i;j\nk,l,m");
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        // Comma should win due to better consistency (lower coefficient of variation)
        $this->assertEquals(',', $delimiter, 'Should prefer delimiter with lower coefficient of variation');
    }

    /**
     * Test delimiter detection with empty lines interspersed
     */
    public function testDetectDelimiterWithEmptyLines()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_empty_lines_');
        file_put_contents($tempFile, "col1,col2,col3\n\nval1,val2,val3\n\n\nval4,val5,val6");
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Should handle files with empty lines interspersed');
    }

    /**
     * Test delimiter detection with very few samples
     */
    public function testDetectDelimiterFewSamples()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_few_samples_');
        file_put_contents($tempFile, "col1,col2,col3");
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Should handle files with very few sample lines');
    }

    /**
     * Test delimiter detection with complex quoted content
     */
    public function testDetectDelimiterComplexQuotes()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_complex_quotes_');
        file_put_contents($tempFile, '"field1,with,commas","field2;with;semicolons","field3|with|pipes"' . "\n" . 
                                      '"data1,more,commas","data2;more;semicolons","data3|more|pipes"');
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Should properly handle complex quoted content with embedded delimiters');
    }

    /**
     * Test delimiter detection algorithm variance calculation
     */
    public function testDelimiterVarianceCalculation()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_variance_');
        
        // Create file with high variance for semicolon, low variance for comma
        file_put_contents($tempFile, "a,b,c\nd,e,f\ng,h,i\nj;k;l;m;n;o;p\nq,r,s");
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Should prefer delimiter with lower variance in column counts');
    }

    /**
     * Test delimiter detection with extreme field count differences
     */
    public function testDelimiterExtremeFieldCounts()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_extreme_fields_');
        
        // One delimiter creates consistent fields, another creates very inconsistent fields
        file_put_contents($tempFile, "a,b\nc,d\ne,f\ng;h;i;j;k;l;m;n;o;p;q;r;s;t;u;v;w;x;y;z");
        
        $stream = fopen($tempFile, 'r');
        $delimiter = $this->detectDelimiter($stream);
        fclose($stream);
        unlink($tempFile);
        
        $this->assertEquals(',', $delimiter, 'Should prefer delimiter with consistent field counts over inconsistent ones');
    }
}
