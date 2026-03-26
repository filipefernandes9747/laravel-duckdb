<?php

declare(strict_types=1);

namespace LaravelDuckDB\Internal\Result;

use FFI\CData;

trait ValidityTrait
{
    protected function rowIsValid(?CData $validity, int $row): bool
    {
        if ($validity === null) {
            return true;
        }

        $entryIdx = (int)($row / 64);
        $idxInEntry = $row % 64;

        return ($validity[$entryIdx] & (1 << $idxInEntry)) !== 0;
    }
}
