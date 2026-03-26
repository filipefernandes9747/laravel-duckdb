<?php

declare(strict_types=1);

namespace LaravelDuckDB\Internal;

use LaravelDuckDB\Internal\FFI\DuckDB as FFIDuckDB;
use LaravelDuckDB\Internal\DB\DB;
use LaravelDuckDB\Internal\DB\Connection;
use LaravelDuckDB\Internal\Result\ResultSet;
use RuntimeException;

class DuckDB
{
    private DB $db;
    private Connection $connection;
    private static ?FFIDuckDB $ffi = null;

    private function __construct()
    {
    }

    public static function create(?string $path = null): self
    {
        self::init();
        
        $instance = new self();
        $instance->db = new DB(self::$ffi, $path);
        $instance->connection = new Connection($instance->db->db, self::$ffi);
        
        return $instance;
    }

    private static function init(): void
    {
        if (self::$ffi !== null) {
            return;
        }

        $libPath = getenv('DUCKDB_LIB_PATH');
        $headerPath = getenv('DUCKDB_HEADER_PATH');

        if (!$libPath || !$headerPath) {
            // Fallback or specific logic for finding the library if env vars are missing
            // For now, we expect them to be set by the installer or manually
            throw new RuntimeException('DUCKDB_LIB_PATH and DUCKDB_HEADER_PATH must be set.');
        }

        // The libPath might be a directory or a full path to the file
        // DuckDBInstaller sets it to a directory. We need the actual file.
        $libFile = match (PHP_OS_FAMILY) {
            'Windows' => $libPath . DIRECTORY_SEPARATOR . 'duckdb.dll',
            'Darwin'  => $libPath . DIRECTORY_SEPARATOR . 'libduckdb.dylib',
            default   => $libPath . DIRECTORY_SEPARATOR . 'libduckdb.so',
        };
        
        $headerFile = $headerPath . DIRECTORY_SEPARATOR . 'duckdb-ffi.h';

        self::$ffi = new FFIDuckDB($headerFile, $libFile);
    }

    public function query(string $query): ResultSet
    {
        $queryResult = self::$ffi->new('duckdb_result');
        $result = self::$ffi->query($this->connection->connection, $query, self::$ffi->addr($queryResult));

        if ($result === self::$ffi->error()) {
            $error = self::$ffi->resultError(self::$ffi->addr($queryResult));
            self::$ffi->destroyResult(self::$ffi->addr($queryResult));
            throw new RuntimeException('Query failed: ' . $error);
        }

        return new ResultSet(self::$ffi, $queryResult);
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function __destruct()
    {
        if (isset($this->connection) && self::$ffi) {
            self::$ffi->disconnect(self::$ffi->addr($this->connection->connection));
        }
        if (isset($this->db) && self::$ffi) {
            self::$ffi->close(self::$ffi->addr($this->db->db));
        }
    }
}
