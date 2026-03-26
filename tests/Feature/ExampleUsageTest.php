<?php

namespace LaravelDuckDB\Tests\Feature;

use Illuminate\Support\Facades\DB;
use LaravelDuckDB\Tests\TestCase;
use LaravelDuckDB\DuckDBConnection;

class ExampleUsageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('ffi')) {
            $this->markTestSkipped('FFI extension is required for these tests.');
        }
    }

    /**
     * Test querying a CSV file directly.
     */
    public function test_query_csv_file_directly(): void
    {
        $csvPath = __DIR__ . '/test.csv';
        file_put_contents($csvPath, "id,name,score\n1,Alice,95\n2,Bob,88\n3,Charlie,92");

        try {
            // Using the file() helper which defaults to selecting from the file path
            $results = DB::connection('duckdb')
                ->file($csvPath)
                ->where('score', '>', 90)
                ->orderBy('score', 'desc')
                ->get();

            $this->assertCount(2, $results);
            $this->assertEquals('Alice', $results[0]['name']);
            $this->assertEquals('Charlie', $results[1]['name']);
        } finally {
            if (file_exists($csvPath)) {
                unlink($csvPath);
            }
        }
    }

    /**
     * Test complex analytical aggregations.
     */
    public function test_analytical_aggregations(): void
    {
        // Simple in-memory data for aggregation test
        $results = DB::connection('duckdb')
            ->select("
                SELECT 
                    category, 
                    AVG(price) as avg_price, 
                    SUM(price) as total_revenue
                FROM (
                    SELECT 'Electronics' as category, 100 as price
                    UNION ALL SELECT 'Electronics', 200
                    UNION ALL SELECT 'Books', 50
                    UNION ALL SELECT 'Books', 30
                ) as sales
                GROUP BY category
                ORDER BY avg_price DESC
            ");

        $this->assertCount(2, $results);
        $this->assertEquals('Electronics', $results[0]['category']);
        $this->assertEquals(150, $results[0]['avg_price']);
        $this->assertEquals('Books', $results[1]['category']);
        $this->assertEquals(40, $results[1]['avg_price']);
    }

    /**
     * Test the print() macro added to the Query Builder.
     */
    public function test_query_builder_print_macro(): void
    {
        // This test mostly verifies the macro exists and doesn't crash.
        // It outputs to stdout which DuckDB does via FFI.
        
        // Use capture to avoid polluting test output if possible, 
        // though DuckDB FFI usually writes directly to C-level stdout.
        ob_start();
        DB::connection('duckdb')
            ->table(DB::raw("(SELECT 1 as id, 'Demo' as label) as t"))
            ->print();
        $output = ob_get_clean();

        // The macro is registered in DuckDBServiceProvider
        $this->assertTrue(true); 
    }

    /**
     * Test DuckDB specific SQL functions.
     */
    public function test_duckdb_specific_functions(): void
    {
        // Test date_part and string functions
        $result = DB::connection('duckdb')
            ->select("SELECT date_part('year', TIMESTAMP '2026-03-26 14:00:00') as year, upper('duckdb') as name");

        $this->assertEquals(2026, $result[0]['year']);
        $this->assertEquals('DUCKDB', $result[0]['name']);
    }

    /**
     * Test JSON querying.
     */
    public function test_query_json_file(): void
    {
        $jsonPath = __DIR__ . '/test.json';
        file_put_contents($jsonPath, json_encode([
            ['id' => 1, 'data' => ['tags' => ['a', 'b']]],
            ['id' => 2, 'data' => ['tags' => ['c']]],
        ]));

        try {
            // DuckDB can query JSON files directly
            $results = DB::connection('duckdb')
                ->select("SELECT id, data->'tags' as tags FROM read_json_auto(?)", [$jsonPath]);

            $this->assertCount(2, $results);
            // DuckDB JSON returns strings or lists depending on path
            $this->assertStringContainsString('a', $results[0]['tags']);
        } finally {
            if (file_exists($jsonPath)) {
                unlink($jsonPath);
            }
        }
    }
}
