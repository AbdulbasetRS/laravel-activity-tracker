<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Contracts;

interface SensitiveDataSanitizerInterface
{
    /**
     * Strip sensitive keys from an attribute array.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function sanitizeAttributes(array $attributes): array;

    /**
     * Sanitize a flat list of query bindings using positional heuristics
     * combined with known sensitive column names when available.
     *
     * @param  array<int, mixed>  $bindings
     * @return array<int, mixed>
     */
    public function sanitizeBindings(array $bindings): array;
}
