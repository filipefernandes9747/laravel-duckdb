<?php

namespace LaravelDuckDB;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Connection;

class DuckDBServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->resolving('db', function ($db) {
            $db->extend('duckdb', function ($config, $name) {
                $config['name'] = $name;

                $connector = new DuckDBConnector;
                $connection = $connector->connect($config);

                return new DuckDBConnection($connection, $config['database'], $config['prefix'] ?? '', $config);
            });
        });
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
