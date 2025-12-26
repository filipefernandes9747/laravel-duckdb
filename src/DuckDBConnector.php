<?php

namespace LaravelDuckDB;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;
use Saturio\DuckDB\DuckDB;

use Saturio\DuckDB\DB\Configuration;

class DuckDBConnector extends Connector implements ConnectorInterface
{
    /**
     * Establish a database connection.
     *
     * @param  array  $config
     * @return \Saturio\DuckDB\DuckDB
     */
    public function connect(array $config)
    {
        // $path = $config['database'] ?? ':memory:';
        
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

        // Pass a config object (even if empty) to trigger openExt which handles errors better in the library logic
        return DuckDB::create($path, $configuration);
    }
}
