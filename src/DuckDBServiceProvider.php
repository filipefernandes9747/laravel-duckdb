<?php

namespace LaravelDuckDB;

use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;
use LaravelDuckDB\Installer\DuckDBInstaller;
use Throwable;

class DuckDBServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        // Merge package config so it can be overridden by the app's config/duckdb.php
        $this->mergeConfigFrom(__DIR__ . '/../config/duckdb.php', 'duckdb');

        // Register the installer as a singleton
        $this->app->singleton(DuckDBInstaller::class, function ($app) {
            $config = $app['config']->get('duckdb.installer');

            $path    = $config['path'] ?? storage_path('duckdb');
            $version = $config['version'] ?? '1.2.1';

            return new DuckDBInstaller($path, $version);
        });

        // Register the DuckDB database driver
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
     * Bootstrap package services.
     */
    public function boot(): void
    {
        // Publish the config file
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/duckdb.php' => config_path('duckdb.php'),
            ], 'duckdb-config');
        }

        // Run the auto-installer (if enabled)
        $this->runInstaller();

        // Register the Builder::print() macro for DuckDB connections
        \Illuminate\Database\Query\Builder::macro('print', function () {
            $connection = $this->getConnection();

            if ($connection instanceof DuckDBConnection) {
                $sql          = $this->toSql();
                $bindings     = $this->getBindings();
                $interpolated = $connection->interpolateQuery($sql, $bindings);
                $result       = $connection->getConnection()->query($interpolated);
                $result->print();
            } else {
                throw new \BadMethodCallException('print() is only available for DuckDB connections.');
            }
        });
    }

    /**
     * Invoke the DuckDB installer unless disabled by config.
     */
    protected function runInstaller(): void
    {
        if (! $this->app['config']->get('duckdb.installer.enabled', true)) {
            return;
        }

        try {
            /** @var DuckDBInstaller $installer */
            $installer = $this->app->make(DuckDBInstaller::class);
            $installer->install();
        } catch (Throwable $e) {
            // Log the failure but do NOT crash the application —
            // the user may be running a custom setup or the storage path may not yet exist.
            logger()->error('[DuckDB Installer] Installation failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
