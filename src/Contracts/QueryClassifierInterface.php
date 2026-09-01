<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Contracts;

interface QueryClassifierInterface
{
    /**
     * Classify a raw SQL string into a normalized operation type such as
     * select, insert, update, delete, count, exists, sum, avg, min, max,
     * or unknown.
     */
    public function classify(string $sql): string;

    /**
     * Extract the primary table name referenced by the query, if detectable.
     */
    public function extractTable(string $sql): ?string;
}
