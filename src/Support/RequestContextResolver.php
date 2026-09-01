<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Support;

use Illuminate\Http\Request;
use Throwable;

/**
 * Extracts optional HTTP request metadata. The package must function
 * identically in CLI/queue/console contexts, so every accessor here
 * degrades to null rather than throwing when no request is bound.
 */
final class RequestContextResolver
{
    public function __construct(private readonly ?Request $request = null)
    {
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

        return $this->safe(fn () => $this->request?->userAgent());
    }

    public function url(): ?string
    {
        if (! config('activity-tracker.context.capture_url', true)) {
            return null;
        }

        return $this->safe(fn () => $this->request?->fullUrl());
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

    private function safe(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return null;
        }
    }
}
