<?php

declare(strict_types=1);

namespace LaravelDuckDB\Internal\Result;

use LaravelDuckDB\Internal\FFI\DuckDB as FFIDuckDB;
use FFI\CData;
use Iterator;

class ResultSet
{
    private ?int $columnCount = null;

    public function __construct(
        private readonly FFIDuckDB $ffi,
        private readonly CData $result
    ) {
    }

    public function fetchChunk(): ?DataChunk
    {
        $newChunk = $this->ffi->fetchChunk($this->result);

        return $newChunk ? new DataChunk($this->ffi, $newChunk) : null;
    }

    public function chunks(): iterable
    {
        while ($chunk = $this->fetchChunk()) {
            yield $chunk;
        }
    }

    public function rows(bool $columnNameAsKey = true): Iterator
    {
        foreach ($this->chunks() as $chunk) {
            $rowCount = $chunk->rowCount();
            $this->columnCount ??= $chunk->columnCount();
            
            $columnNames = [];
            $vectors = [];

            for ($i = 0; $i < $this->columnCount; $i++) {
                $columnNames[] = $this->columnName($i);
                $vectors[] = $chunk->getVector($i);
            }

            for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
                $row = [];
                foreach ($vectors as $i => $vector) {
                    $key = $columnNameAsKey ? $columnNames[$i] : $i;
                    $row[$key] = $vector->getTypedData($rowIndex);
                }
                yield $row;
            }
        }
    }

    public function columnName(int $columnIndex): ?string
    {
        return $this->ffi->columnName($this->ffi->addr($this->result), $columnIndex);
    }

    public function columnCount(): int
    {
        return $this->ffi->columnCount($this->ffi->addr($this->result));
    }

    public function columnNames(): iterable
    {
        for ($i = 0; $i < $this->columnCount(); $i++) {
            yield $i => $this->columnName($i);
        }
    }

    public function print(): void
    {
        $rows = iterator_to_array($this->rows());
        if (empty($rows)) {
            echo "Empty result set.\n";
            return;
        }

        $columnNames = array_keys($rows[0]);
        $widths = [];

        foreach ($columnNames as $name) {
            $widths[$name] = strlen((string)$name);
        }

        foreach ($rows as $row) {
            foreach ($columnNames as $name) {
                $widths[$name] = max($widths[$name], strlen((string)($row[$name] ?? '')));
            }
        }

        // Header separator
        $line = "+-" . implode("-+-", array_map(fn($w) => str_repeat("-", $w), $widths)) . "-+\n";
        
        echo $line;
        echo "| " . implode(" | ", array_map(fn($name) => str_pad((string)$name, $widths[$name]), $columnNames)) . " |\n";
        echo $line;

        foreach ($rows as $row) {
            echo "| " . implode(" | ", array_map(fn($name) => str_pad((string)($row[$name] ?? ''), $widths[$name]), $columnNames)) . " |\n";
        }
        echo $line;
    }

    public function __destruct()
    {
        $this->ffi->destroyResult($this->ffi->addr($this->result));
    }
}
