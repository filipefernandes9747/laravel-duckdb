<?php

declare(strict_types=1);

namespace LaravelDuckDB\Internal\Result;

use LaravelDuckDB\Internal\FFI\DuckDB as FFIDuckDB;
use LaravelDuckDB\Internal\Type\DuckDBType;
use FFI\CData;
use FFI;

class Vector
{
    use ValidityTrait;

    private DuckDBType $type;
    private CData $typedData;
    private CData $logicalType;
    private ?CData $validity;

    public function __construct(
        private readonly FFIDuckDB $ffi,
        private readonly CData $vector,
        public readonly int $rows
    ) {
        $this->logicalType = $this->ffi->vectorGetColumnType($this->vector);
        $this->type = DuckDBType::from($this->ffi->getTypeId($this->logicalType));
        
        $this->typedData = $this->ffi->cast(
            $this->type->toCName() . ' *',
            $this->ffi->vectorGetData($this->vector)
        );

        $this->validity = $this->ffi->vectorGetValidity($this->vector);
        if ($this->validity !== null) {
            $this->validity = $this->ffi->cast('uint64_t *', $this->validity);
        }
    }

    public function getTypedData(int $rowIndex): mixed
    {
        if (!$this->rowIsValid($this->validity, $rowIndex)) {
            return null;
        }

        $data = $this->typedData[$rowIndex];

        return match ($this->type) {
            DuckDBType::BOOLEAN => (bool)$data,
            DuckDBType::TINYINT,
            DuckDBType::SMALLINT,
            DuckDBType::INTEGER,
            DuckDBType::BIGINT => (int)$data,
            DuckDBType::UTINYINT,
            DuckDBType::USMALLINT,
            DuckDBType::UINTEGER,
            DuckDBType::UBIGINT => (int)$data, // Note: might overflow on 32-bit PHP for UBIGINT
            DuckDBType::FLOAT,
            DuckDBType::DOUBLE => (float)$data,
            DuckDBType::VARCHAR => $this->getVarChar($data),
            default => $data,
        };
    }

    private function getVarChar(CData $data): string
    {
        // duckdb_string_t handling
        $length = $data->value->inlined->length;
        if ($length <= 12) {
            return FFI::string($data->value->inlined->inlined, $length);
        }
        
        return FFI::string($data->value->pointer->ptr, $length);
    }

    public function __destruct()
    {
        // Only destroy if we own it, but vectorGetColumnType returns a non-owning logical type usually?
        // Actually satur.io destroys it.
        $this->ffi->destroyLogicalType($this->ffi->addr($this->logicalType));
    }
}
