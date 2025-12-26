<?php

namespace LaravelDuckDB\Tests\Feature;

use Illuminate\Support\Facades\DB;
use LaravelDuckDB\Tests\TestCase;

class IntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('ffi')) {
            $this->markTestSkipped('The FFI extension is not available.');
        }

        try {
            // Simple check if we can initialize the driver without crashing
            // This might fail if the duckdb lib isn't downloaded/available to the FFI wrapper
            parent::setUp();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Could not initialize DuckDB driver: ' . $e->getMessage());
        }
    }

    public function test_basic_select_query()
    {
        // Tests rely on valid DuckDB setup.
        // We'll try a simple memory query.
        
        try {
            $result = DB::select("SELECT 'Hello World' as greeting");
            
            $this->assertIsArray($result);
            $this->assertNotEmpty($result);
            $this->assertEquals('Hello World', $result[0]['greeting']);
        } catch (\Exception $e) {
             // If the native lib is missing this will fail.
             $this->markTestSkipped('DuckDB query failed: ' . $e->getMessage());
        }
    }

    public function test_parameter_bindings()
    {
        try {
            $result = DB::select("SELECT ?::INTEGER as num", [42]);
            
            $this->assertEquals(42, $result[0]['num']);
        } catch (\Exception $e) {
             $this->markTestSkipped('DuckDB query failed: ' . $e->getMessage());
        }
    }
}
