<?php

declare(strict_types=1);

namespace LaravelDuckDB\Internal\FFI;

use FFI;
use FFI\CType;
use FFI\CData;
use RuntimeException;

class DuckDB
{
    private static ?FFI $ffi = null;

    public function __construct(?string $headerPath = null, ?string $libPath = null)
    {
        if (self::$ffi === null) {
            if ($headerPath === null || $libPath === null) {
                throw new RuntimeException('DuckDB header and library paths must be provided for the first initialization.');
            }

            if (!file_exists($headerPath)) {
                throw new RuntimeException("DuckDB header file not found: {$headerPath}");
            }

            if (!file_exists($libPath)) {
                throw new RuntimeException("DuckDB library file not found: {$libPath}");
            }

            try {
                self::$ffi = FFI::cdef(file_get_contents($headerPath), $libPath);
            } catch (FFI\Exception $e) {
                throw new RuntimeException("Failed to load DuckDB via FFI: " . $e->getMessage(), 0, $e);
            }
        }
    }

    public function new(string|CType $name, bool $owned = true): ?CData
    {
        return self::$ffi->new($name, $owned);
    }

    public function addr(CData $name): ?CData
    {
        return FFI::addr($name);
    }

    public function free(CData $pointer): void
    {
        FFI::free($pointer);
    }

    public function duckDBFree(CData $pointer): void
    {
        self::$ffi->duckdb_free($pointer);
    }

    public function type(string $type): CType
    {
        return self::$ffi->type($type);
    }

    public function error(): int
    {
        return 1; // DuckDBError
    }

    public function success(): int
    {
        return 0; // DuckDBSuccess
    }

    public function open(?string $path, CData $database): int
    {
        return self::$ffi->duckdb_open($path, $database);
    }

    public function close(CData $database): void
    {
        self::$ffi->duckdb_close($database);
    }

    public function connect(CData $database, CData $connection): int
    {
        return self::$ffi->duckdb_connect($database, $connection);
    }

    public function disconnect(CData $connection): void
    {
        self::$ffi->duckdb_disconnect($connection);
    }

    public function query(CData $connection, string $query, CData $result): int
    {
        return self::$ffi->duckdb_query($connection, $query, $result);
    }

    public function resultError(CData $result): ?string
    {
        $error = self::$ffi->duckdb_result_error($result);
        return $error !== null ? (string)$error : null;
    }

    public function columnCount(CData $result): int
    {
        return (int)self::$ffi->duckdb_column_count($result);
    }

    public function columnName(CData $result, int $index): ?string
    {
        $name = self::$ffi->duckdb_column_name($result, (int)$index);
        return $name !== null ? (string)$name : null;
    }

    public function destroyResult(CData $result): void
    {
        self::$ffi->duckdb_destroy_result($result);
    }

    public function duckdbPrintResult(CData $result): void
    {
        self::$ffi->duckdb_print_result($result);
    }

    public function fetchChunk(CData $result): ?CData
    {
        return self::$ffi->duckdb_fetch_chunk($result);
    }

    public function dataChunkGetSize(CData $dataChunk): int
    {
        return (int)self::$ffi->duckdb_data_chunk_get_size($dataChunk);
    }

    public function dataChunkGetColumnCount(CData $dataChunk): int
    {
        return (int)self::$ffi->duckdb_data_chunk_get_column_count($dataChunk);
    }

    public function dataChunkGetVector(CData $dataChunk, int $columnIndex): CData
    {
        return self::$ffi->duckdb_data_chunk_get_vector($dataChunk, (int)$columnIndex);
    }

    public function vectorGetData(CData $vector): CData
    {
        return self::$ffi->duckdb_vector_get_data($vector);
    }

    public function vectorGetValidity(CData $vector): ?CData
    {
        return self::$ffi->duckdb_vector_get_validity($vector);
    }

    public function validityRowIsValid(CData $validity, int $row): bool
    {
        return (bool)self::$ffi->duckdb_validity_row_is_valid($validity, (int)$row);
    }

    public function vectorGetColumnType(CData $vector): CData
    {
        return self::$ffi->duckdb_vector_get_column_type($vector);
    }

    public function getTypeId(CData $logicalType): int
    {
        return (int)self::$ffi->duckdb_get_type_id($logicalType);
    }

    public function destroyDataChunk(CData $dataChunk): void
    {
        self::$ffi->duckdb_destroy_data_chunk($dataChunk);
    }

    public function cast(string $type, CData $data): CData
    {
        return self::$ffi->cast($type, $data);
    }

    public static function string(CData $string, ?int $length = null): string
    {
        return FFI::string($string, $length);
    }

    public function destroyLogicalType(CData $logicalType): void
    {
        self::$ffi->duckdb_destroy_logical_type($logicalType);
    }
}
