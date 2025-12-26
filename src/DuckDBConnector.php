<?php

namespace LaravelDuckDB;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;
use Satur\DuckDB\DuckDB;

class DuckDBConnector extends Connector implements ConnectorInterface
{
    /**
     * Establish a database connection.
     *
     * @param  array  $config
     * @return \Satur\DuckDB\DuckDB
     */
    public function connect(array $config)
    {
        // $path = $config['database'] ?? ':memory:';
        // The Satur\DuckDB library initialization logic.
        // Assuming: new DuckDB($path)
        
        $path = $config['database'] ?? ':memory:';
        
        return new DuckDB($path);
    }
}
