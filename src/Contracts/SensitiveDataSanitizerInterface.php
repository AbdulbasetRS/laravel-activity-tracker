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

    /**
     * Redact configured sensitive query-string parameters from a URL,
     * preserving the rest of the URL and the parameter names themselves —
     * only the value is replaced. Malformed URLs are returned unchanged
     * (never throws) since this handles untrusted input (e.g. the Referer
     * header).
     */
    public function sanitizeUrl(string $url): string;

    /**
     * Mask a user-supplied authentication identifier (email or username)
     * for safe display — e.g. "ahmed@example.com" -> "a***@example.com",
     * "ahmed123" -> "a***3". Never returns the original value unmasked.
     */
    public function maskIdentifier(string $identifier): string;
}
