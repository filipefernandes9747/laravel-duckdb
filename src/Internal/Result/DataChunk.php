<?php

declare(strict_types=1);

namespace LaravelDuckDB\Internal\Result;

use LaravelDuckDB\Internal\FFI\DuckDB as FFIDuckDB;
use FFI\CData;

class DataChunk
{
    public function __construct(
        private readonly FFIDuckDB $ffi,
        private readonly CData $dataChunk
    ) {
    }

    public function rowCount(): int
    {
        return $this->ffi->dataChunkGetSize($this->dataChunk);
    }

    public function columnCount(): int
    {
        return $this->ffi->dataChunkGetColumnCount($this->dataChunk);
    }

    public function getVector(int $columnIndex): Vector
    {
        return new Vector(
            $this->ffi,
            $this->ffi->dataChunkGetVector($this->dataChunk, $columnIndex),
            $this->rowCount()
        );
    }

    public function __destruct()
    {
        $this->ffi->destroyDataChunk($this->ffi->addr($this->dataChunk));
    }
}
