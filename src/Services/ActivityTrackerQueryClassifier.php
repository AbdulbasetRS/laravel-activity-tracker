<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Services;

use Abdulbaset\ActivityTracker\Contracts\QueryClassifierInterface;

/**
 * Classifies raw SQL strings into normalized operation types.
 *
 * This is deliberately NOT a full SQL parser. Laravel's query builder
 * generates a well-known, fairly narrow set of SQL shapes, and this
 * classifier is tuned for those shapes rather than attempting to handle
 * arbitrary hand-written SQL perfectly. It is designed to be extended:
 * additional patterns can be registered via `extendAggregatePattern()` or
 * by decorating/subclassing this class and rebinding the contract.
 */
class ActivityTrackerQueryClassifier implements QueryClassifierInterface
{
    /**
     * Additional regex => classification pairs, checked before the built-in
     * aggregate detection. Allows applications to teach the classifier about
     * custom SQL shapes without forking the package.
     *
     * @var array<string, string>
     */
    private array $customPatterns = [];

    public function classify(string $sql): string
    {
        $normalized = $this->normalize($sql);

        foreach ($this->customPatterns as $pattern => $classification) {
            if (preg_match($pattern, $normalized) === 1) {
                return $classification;
            }
        }

        if ($normalized === '') {
            return 'unknown';
        }

        $verb = $this->leadingVerb($normalized);

        return match ($verb) {
            'insert' => 'insert',
            'update' => 'update',
            'delete' => 'delete',
            'select' => $this->classifySelect($normalized),
            default => 'unknown',
        };
    }

    public function extractTable(string $sql): ?string
    {
        $normalized = $this->normalize($sql);

        // Covers: insert into `table`, update `table`, delete from `table`,
        // select ... from `table`.
        $patterns = [
            '/\binsert\s+into\s+["`\[]?([a-z0-9_\.]+)["`\]]?/i',
            '/\bupdate\s+["`\[]?([a-z0-9_\.]+)["`\]]?/i',
            '/\bdelete\s+from\s+["`\[]?([a-z0-9_\.]+)["`\]]?/i',
            '/\bfrom\s+["`\[]?([a-z0-9_\.]+)["`\]]?/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized, $matches) === 1) {
                $table = $matches[1];

                // Strip database/schema prefix (e.g. "app.users" -> "users").
                if (str_contains($table, '.')) {
                    $parts = explode('.', $table);
                    $table = end($parts);
                }

                return $table;
            }
        }

        return null;
    }

    /**
     * Register a custom classification pattern. The pattern is matched
     * against the normalized (lowercased, whitespace-collapsed) SQL.
     */
    public function extendPattern(string $regex, string $classification): void
    {
        $this->customPatterns[$regex] = $classification;
    }

    private function classifySelect(string $normalized): string
    {
        // EXISTS queries in Laravel are typically:
        // "select exists(select * from `table` where ...) as `exists`"
        if (str_contains($normalized, 'select exists(') || str_contains($normalized, 'select exists (')) {
            return 'exists';
        }

        // Aggregate queries are typically:
        // "select count(*) as aggregate from ..."
        // "select sum(`col`) as aggregate from ..."
        if (preg_match('/select\s+count\(/', $normalized) === 1) {
            return 'count';
        }

        if (preg_match('/select\s+sum\(/', $normalized) === 1) {
            return 'sum';
        }

        if (preg_match('/select\s+avg\(/', $normalized) === 1) {
            return 'avg';
        }

        if (preg_match('/select\s+min\(/', $normalized) === 1) {
            return 'min';
        }

        if (preg_match('/select\s+max\(/', $normalized) === 1) {
            return 'max';
        }

        return 'select';
    }

    private function leadingVerb(string $normalized): ?string
    {
        if (preg_match('/^\(*\s*([a-z]+)/', $normalized, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Normalize SQL for reliable pattern matching:
     * - lowercase
     * - collapse whitespace
     * - strip backticks/double-quotes used as identifier quoting
     * - trim leading parentheses noise from subqueries
     */
    private function normalize(string $sql): string
    {
        $sql = strtolower($sql);
        $sql = str_replace(['`', '"'], '', $sql);
        $sql = preg_replace('/\s+/', ' ', $sql) ?? $sql;

        return trim($sql);
    }
}
