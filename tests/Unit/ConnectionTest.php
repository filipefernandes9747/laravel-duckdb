<?php

namespace LaravelDuckDB\Tests\Unit;

use LaravelDuckDB\DuckDBConnection;
use LaravelDuckDB\Tests\TestCase;
use Mockery;
use Saturio\DuckDB\DuckDB;
use ReflectionMethod;

class ConnectionTest extends TestCase
{
    public function test_it_interpolates_bindings_correctly()
    {
        $duckDB = Mockery::mock(DuckDB::class);
        $connection = new DuckDBConnection($duckDB);

        $method = new ReflectionMethod(DuckDBConnection::class, 'interpolateQuery');
        $method->setAccessible(true);

        $query = 'SELECT * FROM users WHERE id = ? AND name = ?';
        $bindings = [1, 'John Doe'];

        $sql = $method->invoke($connection, $query, $bindings);

        $this->assertEquals("SELECT * FROM users WHERE id = 1 AND name = 'John Doe'", $sql);
    }

    public function test_it_escapes_strings_correctly()
    {
        $duckDB = Mockery::mock(DuckDB::class);
        $connection = new DuckDBConnection($duckDB);

        $method = new ReflectionMethod(DuckDBConnection::class, 'escape');
        $method->setAccessible(true);

        $this->assertEquals("'O''Reilly'", $method->invoke($connection, "O'Reilly"));
    }

    public function test_it_handles_null_values()
    {
        $duckDB = Mockery::mock(DuckDB::class);
        $connection = new DuckDBConnection($duckDB);

        $method = new ReflectionMethod(DuckDBConnection::class, 'escape');
        $method->setAccessible(true);

        $this->assertEquals('NULL', $method->invoke($connection, null));
    }
    
    public function test_it_handles_booleans()
    {
        $duckDB = Mockery::mock(DuckDB::class);
        $connection = new DuckDBConnection($duckDB);

        $method = new ReflectionMethod(DuckDBConnection::class, 'escape');
        $method->setAccessible(true);

        $this->assertEquals('TRUE', $method->invoke($connection, true));
        $this->assertEquals('FALSE', $method->invoke($connection, false));
    }

    public function test_it_proxies_file_to_table()
    {
        $duckDB = Mockery::mock(DuckDB::class);
        $connection = new DuckDBConnection($duckDB);

        $query = $connection->file('data.parquet');

        $this->assertInstanceOf(\Illuminate\Database\Query\Builder::class, $query);
        $this->assertEquals("select * from 'data.parquet'", $query->toSql());
    }
}
