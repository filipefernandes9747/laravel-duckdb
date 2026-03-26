<?php

declare(strict_types=1);

namespace LaravelDuckDB\Internal\DB;

use LaravelDuckDB\Internal\FFI\DuckDB as FFIDuckDB;
use FFI\CData;
use RuntimeException;

class Connection
{
    public CData $connection;

    public function __construct(
        CData $db,
        FFIDuckDB $ffi
    ) {
        $this->connection = $ffi->new('duckdb_connection');

        $result = $ffi->connect($db, $ffi->addr($this->connection));

        if ($result === $ffi->error()) {
            $ffi->disconnect($ffi->addr($this->connection));
            throw new RuntimeException('Cannot connect to database.');
        }
    }
}
