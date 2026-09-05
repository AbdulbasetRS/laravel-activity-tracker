<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Listeners;

use Abdulbaset\ActivityTracker\Contracts\ActivityLoggerInterface;
use Abdulbaset\ActivityTracker\Contracts\SensitiveDataSanitizerInterface;
use Abdulbaset\ActivityTracker\Support\TrackingContext;
use Illuminate\Auth\Access\Response as AccessResponse;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Observes Laravel's own authentication lifecycle events — never overrides
 * or replaces any of Laravel's auth behavior, purely listens. Every handler
 * is wrapped so a tracking failure can never break login/logout/password
 * reset for a real user.
 *
 * Only events Laravel's core authentication system reliably fires are
 * implemented. Notably absent, and intentionally so (see README §
 * Authentication tracking): "password_changed" and "password_reset_requested"
 * have no dedicated core Laravel event to hook (a password change is just a
 * User update, already covered — sanitized — by CRUD tracking); "account_
 * locked"/"account_unlocked" don't exist in core Laravel, only the temporary
 * throttling `Lockout` event does, which is what `authentication_throttled`
 * reports.
 */
final class ActivityTrackerAuthenticationTracker
{
    public function __construct(
        private readonly ActivityLoggerInterface $tracker,
        private readonly SensitiveDataSanitizerInterface $sanitizer,
        private readonly TrackingContext $trackingContext,
    ) {
    }

    public function handleAttempting(Attempting $event): void
    {
        $this->safely(function () use ($event) {
            if (! $this->enabled()) {
                return;
            }

            // Keyed by guard name (hashed to an int) rather than a model
            // object — there is no "subject" yet at the attempt stage.
            $this->trackingContext->startTimer($this->timerKey($event->guard));
        });
    }

    public function handleLogin(Login $event): void
    {
        $this->safely(function () use ($event) {
            if (! $this->enabled() || ! $this->trackAction('login')) {
                return;
            }

            $user = $event->user;

            $this->tracker->logAuthEvent('login', [
                'guard' => $event->guard,
                'provider' => $this->providerFor($event->guard),
                'causer_type' => $user instanceof Model ? $user->getMorphClass() : null,
                'causer_id' => $user instanceof Model ? $user->getKey() : null,
                'duration_ms' => $this->trackingContext->stopTimer($this->timerKey($event->guard)),
                'metadata' => ['remember' => $event->remember],
            ]);
        });
    }

    public function handleFailed(Failed $event): void
    {
        $this->safely(function () use ($event) {
            if (! $this->enabled() || ! $this->trackAction('login_failed')) {
                return;
            }

            $this->tracker->logAuthEvent('login_failed', [
                'guard' => $event->guard,
                'provider' => $this->providerFor($event->guard),
                'identifier' => $this->maskedIdentifier($event->credentials),
                'duration_ms' => $this->trackingContext->stopTimer($this->timerKey($event->guard)),
            ]);
        });
    }

    public function handleLogout(Logout $event): void
    {
        $this->safely(function () use ($event) {
            if (! $this->enabled() || ! $this->trackAction('logout')) {
                return;
            }

            // The user is still available on the event at this point, even
            // though the guard is about to forget them — capture identity
            // now, before it's gone.
            $user = $event->user;

            $this->tracker->logAuthEvent('logout', [
                'guard' => $event->guard,
                'provider' => $this->providerFor($event->guard),
                'causer_type' => $user instanceof Model ? $user->getMorphClass() : null,
                'causer_id' => $user instanceof Model ? $user->getKey() : null,
            ]);
        });
    }

    /**
     * Off by default — Authenticated fires on essentially every
     * authenticated request (session/token resolution), not just an actual
     * login action, and would otherwise reproduce the exact "retrieved
     * User" noise problem this package already fixed once. See README.
     */
    public function handleAuthenticated(Authenticated $event): void
    {
        $this->safely(function () use ($event) {
            if (! $this->enabled() || ! $this->trackAction('authenticated', default: false)) {
                return;
            }

            $user = $event->user;

            $this->tracker->logAuthEvent('authenticated', [
                'guard' => $event->guard,
                'provider' => $this->providerFor($event->guard),
                'causer_type' => $user instanceof Model ? $user->getMorphClass() : null,
                'causer_id' => $user instanceof Model ? $user->getKey() : null,
            ]);
        });
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $this->safely(function () use ($event) {
            if (! $this->enabled() || ! $this->trackAction('password_reset')) {
                return;
            }

            $user = $event->user;

            $this->tracker->logAuthEvent('password_reset', [
                'causer_type' => $user instanceof Model ? $user->getMorphClass() : null,
                'causer_id' => $user instanceof Model ? $user->getKey() : null,
            ]);
        });
    }

    public function handleVerified(Verified $event): void
    {
        $this->safely(function () use ($event) {
            if (! $this->enabled() || ! $this->trackAction('email_verified')) {
                return;
            }

            $user = $event->user;

            $this->tracker->logAuthEvent('email_verified', [
                'causer_type' => $user instanceof Model ? $user->getMorphClass() : null,
                'causer_id' => $user instanceof Model ? $user->getKey() : null,
            ]);
        });
    }

    public function handleLockout(Lockout $event): void
    {
        $this->safely(function () use ($event) {
            if (! $this->enabled() || ! $this->trackAction('authentication_throttled')) {
                return;
            }

            $identifierField = (string) config('activity-tracker.authentication.identifier_field', 'email');
            $identifier = $event->request->input($identifierField);

            $this->tracker->logAuthEvent('authentication_throttled', [
                'identifier' => is_string($identifier) ? $this->sanitizer->maskIdentifier($identifier) : null,
            ]);
        });
    }

    /**
     * Registered via Gate::after() — the standard, documented Laravel
     * mechanism for observing every authorization check's outcome without
     * altering it. Only denials are recorded; allowed checks are ignored
     * entirely (they're not a security-relevant signal).
     *
     * @param  array<int, mixed>  $arguments
     */
    public function handleGateCheck(mixed $user, string $ability, AccessResponse|bool $result, array $arguments): void
    {
        $this->safely(function () use ($user, $ability, $result, $arguments) {
            if (! $this->enabled() || ! $this->trackAction('authorization_denied')) {
                return;
            }

            $allowed = $result instanceof AccessResponse ? $result->allowed() : (bool) $result;

            if ($allowed) {
                return;
            }

            $subject = $arguments[0] ?? null;

            $this->tracker->logAuthEvent('authorization_denied', [
                'causer_type' => $user instanceof Model ? $user->getMorphClass() : null,
                'causer_id' => $user instanceof Model ? $user->getKey() : null,
                'subject_type' => $subject instanceof Model ? $subject->getMorphClass() : null,
                'subject_id' => $subject instanceof Model ? $subject->getKey() : null,
                'http_status' => 403,
                'metadata' => ['ability' => $ability],
            ]);
        });
    }

    private function enabled(): bool
    {
        return (bool) config('activity-tracker.authentication.enabled', true);
    }

    private function trackAction(string $action, bool $default = true): bool
    {
        return (bool) config("activity-tracker.authentication.track.{$action}", $default);
    }

    private function providerFor(?string $guard): ?string
    {
        if ($guard === null) {
            return null;
        }

        return config("auth.guards.{$guard}.provider");
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function maskedIdentifier(array $credentials): ?string
    {
        // Deliberately only reads the explicitly configured identifier
        // field — never falls back to "the first credential" (that array
        // typically also contains the plaintext password; guessing could
        // mean masking and storing a fragment of it by mistake).
        $field = (string) config('activity-tracker.authentication.identifier_field', 'email');
        $value = $credentials[$field] ?? null;

        return is_string($value) && $value !== '' ? $this->sanitizer->maskIdentifier($value) : null;
    }

    private function timerKey(?string $guard): int
    {
        return crc32('auth-attempt:'.($guard ?? 'default'));
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable) {
            // Tracking a security-sensitive lifecycle event must never
            // itself break login/logout/authorization for a real user.
        }
    }
}
