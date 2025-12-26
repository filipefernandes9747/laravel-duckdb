<?php

namespace LaravelDuckDB\Tests;

use LaravelDuckDB\DuckDBServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            DuckDBServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'duckdb');
        $app['config']->set('database.connections.duckdb', [
            'driver' => 'duckdb',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
