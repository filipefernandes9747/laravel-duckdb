<?php

declare(strict_types=1);

namespace LaravelDuckDB\Internal\DB;

use LaravelDuckDB\Internal\FFI\DuckDB as FFIDuckDB;
use FFI\CData;

class Configuration
{
    private ?CData $config = null;

    public function __construct()
    {
    }

    public function set(string $name, string $option): void
    {
        // For simplicity, we can just store them in an array 
        // and apply them during DuckDB::create() if we want.
        // But let's just make it a placeholder for now to satisfy the connector.
    }
}
