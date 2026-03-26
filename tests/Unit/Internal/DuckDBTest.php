<?php

namespace LaravelDuckDB\Tests\Unit\Internal;

use LaravelDuckDB\Internal\DuckDB;
use LaravelDuckDB\Internal\Result\ResultSet;
use LaravelDuckDB\Tests\TestCase;
use LaravelDuckDB\Installer\DuckDBInstaller;

class DuckDBTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('ffi')) {
            $this->markTestSkipped('FFI extension is not loaded.');
        }

        // Ensure DuckDB is installed in a temp directory for testing
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duckdb_test_' . uniqid();
        $installer = new DuckDBInstaller($tmpDir, '1.2.1');
        
        try {
            $installer->install();
        } catch (\Exception $e) {
            $this->markTestSkipped('Could not install DuckDB: ' . $e->getMessage());
        }
    }

    public function test_can_create_duckdb_instance(): void
    {
        $duckdb = DuckDB::create();
        $this->assertInstanceOf(DuckDB::class, $duckdb);
    }

    public function test_can_run_simple_query(): void
    {
        $duckdb = DuckDB::create();
        $result = $duckdb->query("SELECT 42 as value, 'hello' as string, true as bool");

        $this->assertInstanceOf(ResultSet::class, $result);
        
        $rows = iterator_to_array($result->rows());
        $this->assertCount(1, $rows);
        
        $row = $rows[0];
        $this->assertEquals(42, $row['value']);
        $this->assertEquals('hello', $row['string']);
        $this->assertTrue($row['bool']);
    }

    public function test_can_fetch_multiple_rows(): void
    {
        $duckdb = DuckDB::create();
        $result = $duckdb->query("SELECT range as i FROM range(5)");

        $rows = iterator_to_array($result->rows());
        $this->assertCount(5, $rows);
        $this->assertEquals(0, $rows[0]['i']);
        $this->assertEquals(4, $rows[4]['i']);
    }

    public function test_column_metadata(): void
    {
        $duckdb = DuckDB::create();
        $result = $duckdb->query("SELECT 1 as a, 2 as b");

        $this->assertEquals(2, $result->columnCount());
        $this->assertEquals('a', $result->columnName(0));
        $this->assertEquals('b', $result->columnName(1));
    }
}
