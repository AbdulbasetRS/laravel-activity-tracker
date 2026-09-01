<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Contracts;

interface ActivityStorageInterface
{
    /**
     * Persist a normalized activity payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function store(array $payload): void;
}
