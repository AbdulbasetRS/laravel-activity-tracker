<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Support;

use Abdulbaset\ActivityTracker\Contracts\SensitiveDataSanitizerInterface;
use Illuminate\Http\Request;

final class RequestContextResolver
{
    public function __construct(
        private readonly SensitiveDataSanitizerInterface $sanitizer,
    ) {
    }

    private function request(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = app('request');

        return $request instanceof Request ? $request : null;
    }

    public function ipAddress(): ?string
    {
        return $this->request()?->ip();
    }

    public function userAgent(): ?string
    {
        return $this->request()?->userAgent();
    }

    public function httpMethod(): ?string
    {
        return $this->request()?->method();
    }

    public function url(): ?string
    {
        $request = $this->request();

        if ($request === null) {
            return null;
        }

        return $this->sanitizer->sanitizeUrl($request->fullUrl());
    }

    public function path(): ?string
    {
        return $this->request()?->path();
    }

    public function referrer(): ?string
    {
        $request = $this->request();

        if ($request === null) {
            return null;
        }

        $referrer = $request->headers->get('referer');

        return $referrer !== null
            ? $this->sanitizer->sanitizeUrl($referrer)
            : null;
    }

    public function routeName(): ?string
    {
        return $this->request()?->route()?->getName();
    }

    public function route(): ?string
    {
        return $this->request()?->route()?->uri();
    }
}