<?php

namespace LaravelDuckDB;

use Illuminate\Database\Connection;
use LaravelDuckDB\Query\Grammar as QueryGrammar;
use LaravelDuckDB\Query\Processor;
use Saturio\DuckDB\DuckDB;
use LaravelDuckDB\Exceptions\DuckDBException;
use DateTimeInterface;

class DuckDBConnection extends Connection
{
    /**
     * The DuckDB connection handler.
     *
     * @var \Saturio\DuckDB\DuckDB
     */
    protected $connection;

    /**
     * Create a new database connection instance.
     *
     * @param  \Saturio\DuckDB\DuckDB  $connection
     * @param  string  $database
     * @param  string  $tablePrefix
     * @param  array  $config
     * @return void
     */
    public function __construct(DuckDB $connection, $database = '', $tablePrefix = '', array $config = [])
    {
        $this->connection = $connection;

        parent::__construct(null, $database, $tablePrefix, $config); // PDO is null
    }

    /**
     * Begin a fluent query against a database table or file.
     *
     * @param  string  $path
     * @param  string|null  $as
     * @return \Illuminate\Database\Query\Builder
     */
    public function file($path, $as = null)
    {
        // Wrap in single quotes for DuckDB file paths
        $wrappedPath = "'".str_replace("'", "''", $path)."'";
        
        return $this->table(new \Illuminate\Database\Query\Expression($wrappedPath), $as);
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \LaravelDuckDB\Query\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        $grammar = new QueryGrammar($this);
        
        // Laravel 11+ Grammars might require connection instance
        if (method_exists($grammar, 'setConnection')) {
            $grammar->setConnection($this);
        }

        $grammar->setTablePrefix($this->tablePrefix);

        return $grammar;
    }

    /**
     * Get the default post processor instance.
     *
     * @return \LaravelDuckDB\Query\Processor
     */
    protected function getDefaultPostProcessor()
    {
        return new Processor;
    }

    /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return [];
            }

            $sql = $this->interpolateQuery($query, $bindings);

            $result = $this->connection->query($sql);
            
            // 'fetchAll' method does not exist on ResultSet.
            // Using iterator_to_array on rows() generator.
            $rows = iterator_to_array($result->rows());
            $columns = iterator_to_array($result->columnNames());
            
            return array_map(function ($row) use ($columns) {
                return array_combine($columns, $row);
            }, $rows);
        });
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return 0;
            }

            $sql = $this->interpolateQuery($query, $bindings);

            $this->connection->query($sql);

            // DuckDB FFI wrapper might not easy return affected rows depending on implementation.
            // For now, return 0 or look for a specific method if available in the future.
            return 0; 
        });
    }

    /**
     * Run an SQL statement.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return true;
            }

            $sql = $this->interpolateQuery($query, $bindings);

            $this->connection->query($sql);

            return true;
        });
    }

    /**
     * Interpolate query bindings.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return string
     */
    protected function interpolateQuery($query, $bindings)
    {
        if (empty($bindings)) {
            return $query;
        }

        foreach ($bindings as $key => $value) {
            $bindings[$key] = $this->escape($value);
        }

        // Replace ? with values
        $query = preg_replace_callback('/\?/', function ($matches) use (&$bindings) {
            return array_shift($bindings);
        }, $query);

        return $query;
    }

    /**
     * Escape a value for safe SQL usage.
     *
     * @param  mixed  $value
     * @return string|int|float
     */
    public function escape($value, $binary = false)
    {
        if (is_null($value)) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

        // Simple escaping for DuckDB strings: Replace ' with '' and wrap in '
        return "'" . str_replace("'", "''", (string) $value) . "'";
    }
}
