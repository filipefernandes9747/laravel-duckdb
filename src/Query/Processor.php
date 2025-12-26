<?php

namespace LaravelDuckDB\Query;

use Illuminate\Database\Query\Processors\Processor as BaseProcessor;

class Processor extends BaseProcessor
{
    /**
     * Process an "insert get ID" query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $sql
     * @param  array  $values
     * @param  string|null  $sequence
     * @return int
     */
    public function processInsertGetId($query, $sql, $values, $sequence = null)
    {
        $query->getConnection()->insert($sql, $values);

        // DuckDB doesn't have reliable last insert ID for all cases without returning clauses or sequences.
        // For basic usage without AUTO_INCREMENT dependent logic, we return 0 or null.
        // If the user needs IDs, they should use UUIDs or handle generation.
        return 0; 
    }
}
