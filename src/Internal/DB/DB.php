<?php

declare(strict_types=1);

namespace LaravelDuckDB\Internal\DB;

use LaravelDuckDB\Internal\FFI\DuckDB as FFIDuckDB;
use FFI\CData;
use RuntimeException;

class DB
{
    public CData $db;

    public function __construct(
        FFIDuckDB $ffi,
        ?string $path = null
    ) {
        $this->db = $ffi->new('duckdb_database');

        $result = $ffi->open($path, $ffi->addr($this->db));

        if ($result === $ffi->error()) {
            $ffi->close($ffi->addr($this->db));
            throw new RuntimeException('Cannot open database.');
        }
    }
}
