<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Support;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Resolves the "causer" of an activity generically, without assuming the
 * authenticatable model is App\Models\User. Works across web, API, and
 * console guards, and degrades gracefully (null) outside HTTP/auth context.
 */
final class CauserResolver
{
    public function __construct(private readonly AuthFactory $auth)
    {
    }

    /**
     * @return array{0: class-string<Model>|null, 1: int|string|null}
     */
    public function resolve(): array
    {
        try {
            $guard = $this->auth->guard();

            if (! $guard->check()) {
                return [null, null];
            }

            $user = $guard->user();

            if (! $user instanceof Model) {
                return [null, null];
            }

            return [$user->getMorphClass(), $user->getKey()];
        } catch (Throwable) {
            // No auth context available (console, early boot, misconfigured
            // guard). This must never break the tracked operation.
            return [null, null];
        }
    }
}
