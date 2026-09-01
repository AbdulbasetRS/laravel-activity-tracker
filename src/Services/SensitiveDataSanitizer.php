<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Services;

use Abdulbaset\ActivityTracker\Contracts\SensitiveDataSanitizerInterface;

final class SensitiveDataSanitizer implements SensitiveDataSanitizerInterface
{
    private const MASK = '***REDACTED***';

    /**
     * @var array<int, string>
     */
    private array $sensitiveColumns;

    public function __construct(?array $sensitiveColumns = null)
    {
        $this->sensitiveColumns = array_map(
            'strtolower',
            $sensitiveColumns ?? (array) config('activity-tracker.sensitive_columns', [])
        );
    }

    public function sanitizeAttributes(array $attributes): array
    {
        $result = [];

        foreach ($attributes as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                // Prefer exclusion entirely over masking: the key itself can
                // still leak information (e.g. "password was changed"), so
                // we drop the key rather than storing a placeholder value.
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    public function sanitizeBindings(array $bindings): array
    {
        // Bindings arrive positionally with no reliable column names, so we
        // cannot map them back to sensitive columns with certainty. Storing
        // raw bindings is opt-in (query_log.store_bindings) specifically
        // because of this limitation; when enabled we still redact obvious
        // long, token/secret-shaped values as a best-effort safety net.
        return array_map(function ($value) {
            if (is_string($value) && $this->looksLikeSecret($value)) {
                return self::MASK;
            }

            return $value;
        }, $bindings);
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach ($this->sensitiveColumns as $sensitive) {
            if ($normalized === $sensitive || str_contains($normalized, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeSecret(string $value): bool
    {
        // Heuristic only: long opaque tokens (hashes, JWTs, API keys) tend to
        // be 32+ chars with no whitespace. This is intentionally conservative
        // documentation-wise: it is NOT a guarantee, hence bindings storage
        // stays disabled by default.
        return strlen($value) >= 32 && ! str_contains($value, ' ');
    }
}
