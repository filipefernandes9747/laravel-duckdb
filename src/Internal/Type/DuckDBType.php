<?php

declare(strict_types=1);

namespace LaravelDuckDB\Internal\Type;

enum DuckDBType: int
{
    case INVALID = 0;
    case BOOLEAN = 1;
    case TINYINT = 2;
    case SMALLINT = 3;
    case INTEGER = 4;
    case BIGINT = 5;
    case UTINYINT = 6;
    case USMALLINT = 7;
    case UINTEGER = 8;
    case UBIGINT = 9;
    case FLOAT = 10;
    case DOUBLE = 11;
    case TIMESTAMP = 12;
    case DATE = 13;
    case TIME = 14;
    case INTERVAL = 15;
    case HUGEINT = 16;
    case UHUGEINT = 32;
    case VARCHAR = 17;
    case BLOB = 18;
    case DECIMAL = 19;
    case TIMESTAMP_S = 20;
    case TIMESTAMP_MS = 21;
    case TIMESTAMP_NS = 22;
    case ENUM = 23;
    case LIST = 24;
    case STRUCT = 25;
    case MAP = 26;
    case ARRAY = 33;
    case UUID = 27;
    case UNION = 28;
    case BIT = 29;
    case TIME_TZ = 30;
    case TIMESTAMP_TZ = 31;

    public function toCName(): string
    {
        return match ($this) {
            self::BOOLEAN => 'bool',
            self::TINYINT => 'int8_t',
            self::SMALLINT => 'int16_t',
            self::INTEGER => 'int32_t',
            self::BIGINT => 'int64_t',
            self::UTINYINT => 'uint8_t',
            self::USMALLINT => 'uint16_t',
            self::UINTEGER => 'uint32_t',
            self::UBIGINT => 'uint64_t',
            self::FLOAT => 'float',
            self::DOUBLE => 'double',
            self::VARCHAR => 'duckdb_string_t',
            self::TIMESTAMP => 'duckdb_timestamp',
            self::DATE => 'duckdb_date',
            self::TIME => 'duckdb_time',
            self::INTERVAL => 'duckdb_interval',
            self::HUGEINT => 'duckdb_hugeint',
            self::UHUGEINT => 'duckdb_uhugeint',
            self::BLOB => 'duckdb_blob',
            self::DECIMAL => 'duckdb_decimal',
            self::TIMESTAMP_S => 'duckdb_timestamp_s',
            self::TIMESTAMP_MS => 'duckdb_timestamp_ms',
            self::TIMESTAMP_NS => 'duckdb_timestamp_ns',
            self::UUID => 'duckdb_uuid',
            self::BIT => 'duckdb_bit',
            self::TIME_TZ => 'duckdb_time_tz',
            self::TIMESTAMP_TZ => 'duckdb_timestamp_tz',
            default => 'void*',
        };
    }
}
