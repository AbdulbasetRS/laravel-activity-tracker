<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Support;

use Abdulbaset\ActivityTracker\Contracts\SensitiveDataSanitizerInterface;
use Illuminate\Http\Request;
use Throwable;

/**
 * Extracts optional HTTP/CLI/database context metadata. The package must
 * function identically in CLI/queue/console contexts, so every accessor
 * here degrades to null rather than throwing when no request is bound (or
 * not applicable to the current execution context).
 *
 * The full request URL — not the route name — is treated as the primary
 * "where did this happen" fact (route_name remains available as secondary
 * metadata): a route can be renamed or absent (e.g. a closure route), while
 * the URL is always what actually happened. Both `url()` and `referrer()`
 * pass through sanitizeUrl() to redact configured sensitive query
 * parameters before ever reaching storage.
 */
final class RequestContextResolver
{
    public function __construct(
        private readonly ?Request $request = null,
        private readonly ?SensitiveDataSanitizerInterface $sanitizer = null,
    ) {
    }

    public function ipAddress(): ?string
    {
        if (! config('activity-tracker.context.capture_ip', true)) {
            return null;
        }

        return $this->safe(fn () => $this->request?->ip());
    }

    public function userAgent(): ?string
    {
        if (! config('activity-tracker.context.capture_user_agent', true)) {
            return null;
        }

        return $this->safe(function () {
            $userAgent = $this->request?->userAgent();

            return $userAgent === null ? null : $this->truncate($userAgent, 'max_user_agent_length', 500);
        });
    }

    public function url(): ?string
    {
        if (! config('activity-tracker.context.capture_url', true)) {
            return null;
        }

        return $this->safe(function () {
            $url = $this->request?->fullUrl();

            if ($url === null) {
                return null;
            }

            $url = $this->sanitizer?->sanitizeUrl($url) ?? $url;

            return $this->truncate($url, 'max_url_length', 2048);
        });
    }

    public function path(): ?string
    {
        if (! config('activity-tracker.context.capture_url', true)) {
            return null;
        }

        return $this->safe(fn () => $this->request?->path());
    }

    /**
     * The HTTP "Referer" header — intentionally exposed here as "referrer"
     * (the commonly-used correct spelling) even though the header itself is
     * misspelled by the HTTP spec. Untrusted input: sanitized and truncated
     * before storage, never rendered as HTML anywhere in the dashboard.
     */
    public function referrer(): ?string
    {
        if (! config('activity-tracker.context.capture_referrer', true)) {
            return null;
        }

        return $this->safe(function () {
            $referrer = $this->request?->headers->get('referer');

            if ($referrer === null || $referrer === '') {
                return null;
            }

            $referrer = $this->sanitizer?->sanitizeUrl($referrer) ?? $referrer;

            return $this->truncate($referrer, 'max_referrer_length', 2048);
        });
    }

    public function method(): ?string
    {
        return $this->safe(fn () => $this->request?->method());
    }

    public function routeName(): ?string
    {
        if (! config('activity-tracker.context.capture_route', true)) {
            return null;
        }

        return $this->safe(fn () => $this->request?->route()?->getName());
    }

    /**
     * The invoked Artisan command's signature name only (e.g. "users:sync")
     * — deliberately NOT the full argv, since command arguments/options may
     * contain sensitive values (e.g. --password=...).
     */
    public function command(): ?string
    {
        if (! app()->runningInConsole()) {
            return null;
        }

        return $this->safe(function () {
            global $argv;

            return (is_array($argv) && isset($argv[1])) ? $argv[1] : null;
        });
    }

    public function databaseConnection(): ?string
    {
        return $this->safe(fn () => (string) config('database.default'));
    }

    private function truncate(string $value, string $configKey, int $default): string
    {
        $max = (int) config("activity-tracker.context.{$configKey}", $default);

        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }

    private function safe(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return null;
        }
    }
}
