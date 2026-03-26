<?php

namespace LaravelDuckDB;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;
use LaravelDuckDB\Internal\DuckDB;
use LaravelDuckDB\Internal\DB\Configuration;

class DuckDBConnector extends Connector implements ConnectorInterface
{
    /**
     * Establish a database connection.
     *
     * @param  array  $config
     * @return \LaravelDuckDB\Internal\DuckDB
     */
    public function connect(array $config)
    {
        $path = $config['database'] ?? ':memory:';

        if ($path === ':memory:') {
            $path = null;
        }

        if ($path) {
            $path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
            $directory = dirname($path);

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }
        
        $configuration = new Configuration;

        if (isset($config['read_only']) && $config['read_only']) {
            $configuration->set('access_mode', 'READ_ONLY');
        }

        return DuckDB::create($path);
    }
}
