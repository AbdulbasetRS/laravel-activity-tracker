<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Master Switch
    |--------------------------------------------------------------------------
    |
    | Globally enable/disable all tracking. Useful for local debugging or
    | disabling the package entirely without removing it.
    |
    */
    'enabled' => env('ACTIVITY_TRACKER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Database Connection & Table
    |--------------------------------------------------------------------------
    */
    'connection' => env('ACTIVITY_TRACKER_CONNECTION', null),

    'table' => 'activities',

    /*
    |--------------------------------------------------------------------------
    | Tracked Operations
    |--------------------------------------------------------------------------
    |
    | Toggle which semantic operations are recorded. Turning an operation off
    | here means it is never persisted, regardless of other settings.
    |
    | NOTE: "count" and "exists" are NOT configurable — they are never
    | tracked, full stop (no usable audit signal; see README). There is
    | intentionally no toggle for them here.
    |
    */
    'track' => [
        'created' => true,
        'updated' => true,
        'deleted' => true,
        'restored' => true,
        'force_deleted' => true,
        'retrieved' => true,
        'aggregates' => true,
        'bulk_updated' => true,
        'bulk_deleted' => true,
        'raw_queries' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retrieval Tracking
    |--------------------------------------------------------------------------
    |
    | Single-model retrieval (find/first/firstWhere) is tracked individually.
    | Collection retrieval (get/all/cursor) is tracked as ONE aggregated
    | "retrieved_many" record to avoid flooding the activity table.
    |
    */
    'retrieval' => [
        'track_single' => true,
        'track_many' => true,

        // Store the actual primary keys retrieved in a collection. Keep this
        // disabled by default for large result sets (memory/storage cost).
        'store_ids' => false,

        // Hard cap on how many IDs to store per retrieved_many record, even
        // when store_ids is enabled.
        'max_ids' => 100,

        // Collections smaller than this are treated the same as "many" for
        // consistency (no threshold-based behavior switch by default).
        'min_many_threshold' => 1,

        // Laravel's auth system resolves the current guard's user via a
        // plain Eloquent retrieval on virtually every authenticated request
        // (auth middleware, Gate checks, auth()->user(), ...). That is a
        // framework mechanic, not a meaningful application read, so every
        // model configured under auth.providers.*.model is excluded from
        // "retrieved"/"retrieved_many" tracking by default. Set to false to
        // audit those reads too (expect one "retrieved" activity per
        // authenticated request).
        'exclude_auth_models' => true,

        // Powers ActivityLoggerInterface::logIntentionalView() — the
        // dashboard's "View subject" action on the Activity Details page
        // uses this to record a deliberate, one-time "viewed via UI" entry,
        // independent of (and never duplicating) the automatic listener.
        'track_ui_views' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Columns
    |--------------------------------------------------------------------------
    |
    | These attribute names are ALWAYS stripped from old_values/new_values/
    | changed_values before persistence, regardless of model.
    |
    */
    'sensitive_columns' => [
        'password',
        'password_confirmation',
        'remember_token',
        'api_token',
        'access_token',
        'refresh_token',
        'secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Models / Tables / Actions
    |--------------------------------------------------------------------------
    */
    'ignored_models' => [
        // App\Models\TemporaryLog::class,
    ],

    'ignored_tables' => [
        'migrations',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'cache',
        'cache_locks',
        'password_reset_tokens',
        'personal_access_tokens',
    ],

    'ignored_actions' => [
        // 'retrieved',
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Logging
    |--------------------------------------------------------------------------
    |
    | Controls whether raw SQL / bindings are stored on activity records that
    | originate from the query listener. Bindings are always sanitized of
    | sensitive_columns values when store_bindings is true.
    |
    */
    'query_log' => [
        'store_sql' => true,
        'store_bindings' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Context
    |--------------------------------------------------------------------------
    */
    'context' => [
        'capture_ip' => true,
        'capture_user_agent' => true,
        'capture_url' => true,
        'capture_route' => true,

        // The HTTP "Referer" header — stored as referrer_url. Never
        // fabricated: absent header means a stored null, not a guess.
        'capture_referrer' => true,

        // Backfilled after the response is sent (see
        // ActivityTrackerRequestLifecycleMiddleware), since the status code
        // genuinely doesn't exist yet at the moment most activities are
        // recorded mid-request.
        'capture_status' => true,

        // Untrusted input, truncated defensively before storage.
        'max_user_agent_length' => 500,
        'max_referrer_length' => 2048,
        'max_url_length' => 2048,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Query Parameters
    |--------------------------------------------------------------------------
    |
    | Applied to both `url` and `referrer_url` before storage. A matching
    | parameter's value is replaced with "[REDACTED]" — the parameter name
    | and the rest of the URL are preserved, since those are usually needed
    | to understand what happened.
    |
    */
    'sensitive_query_parameters' => [
        'token', 'password', 'password_confirmation', 'api_key', 'apikey',
        'secret', 'client_secret', 'access_token', 'refresh_token', 'signature',
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    |
    | duration_ms is measured with hrtime(true) around the actual tracked
    | operation (an Eloquent create/update/delete/restore's underlying query,
    | or Laravel's own QueryExecuted::$time for aggregate/bulk/raw queries) —
    | never the whole HTTP request. It is intentionally left null for
    | retrieved/retrieved_many (a buffered collection has no single
    | meaningful duration) and for intentional UI views (not a measured
    | operation at all). See README § Performance & duration.
    |
    | slow_ms / very_slow_ms only drive the dashboard's Fast/Normal/Slow/
    | Very Slow classification and the "Slow" filter — they never change
    | what gets tracked.
    |
    */
    'performance' => [
        'enabled' => true,
        'track_duration' => true,

        // Off by default: memory_get_usage()/memory_get_peak_usage() add a
        // small but real cost to every tracked operation, and most audit
        // use cases don't need it.
        'track_memory' => false,

        'slow_ms' => 100,
        'very_slow_ms' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception Tracking
    |--------------------------------------------------------------------------
    |
    | Observes Laravel's own exception handler (via a non-invasive decorator
    | registered around the bound Illuminate\Contracts\Debug\ExceptionHandler
    | — see ActivityTrackerExceptionHandlerDecorator) rather than replacing
    | any part of it. A tracker failure while recording an exception can
    | never suppress or replace the original exception/handler behavior.
    |
    | `ignored_exceptions` defaults to the framework's routine "expected"
    | exceptions (validation failures, 404s, auth challenges) — without this,
    | practically every failed login attempt or unmatched route would flood
    | the exception log with non-actionable noise, the same lesson learned
    | from removing count()/exists() tracking.
    |
    */
    'exceptions' => [
        'enabled' => true,

        'store_trace' => true,

        // Truncated (not rejected) beyond this length. NOTE: PHP's default
        // stack trace formatting can include literal scalar arguments
        // passed to functions in the call chain — which could include a
        // plain-text password or token if one was passed as a bare string
        // argument somewhere in the trace. This is a PHP/Laravel behavior,
        // not something this package can selectively redact from a single
        // trace string. Disable store_trace for high-sensitivity
        // applications — see README § Security.
        'max_trace_length' => 10000,

        'ignored_exceptions' => [
            \Illuminate\Validation\ValidationException::class,
            \Illuminate\Auth\AuthenticationException::class,
            \Illuminate\Auth\Access\AuthorizationException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            \Illuminate\Database\Eloquent\ModelNotFoundException::class,
            \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queueing Activity Storage
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'enabled' => env('ACTIVITY_TRACKER_QUEUE_ENABLED', false),
        'connection' => env('ACTIVITY_TRACKER_QUEUE_CONNECTION', null),
        'queue' => env('ACTIVITY_TRACKER_QUEUE_NAME', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention / Pruning
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'enabled' => false,
        'days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Tracking
    |--------------------------------------------------------------------------
    |
    | Observes Laravel's own auth events (login, logout, failed attempts,
    | password reset, email verification, throttling) and, via Gate::after(),
    | authorization denials. Never overrides Laravel's authentication or
    | authorization behavior — purely listens. A tracking failure can never
    | break a real login/logout — see ActivityTrackerAuthenticationTracker.
    |
    | 'authenticated' defaults to OFF: it fires on essentially every
    | authenticated request (session/token resolution), not just an actual
    | login — enabling it is the same trade-off as auditing
    | retrieval.exclude_auth_models = false. Every identifier (email/
    | username) is masked before storage — see maskIdentifier() — never
    | stored in full, and passwords/tokens are never read at all.
    |
    */
    'authentication' => [
        'enabled' => true,

        // Which field in the submitted credentials array identifies the
        // user (for masking on a failed attempt / throttle). Never falls
        // back to "the first credential" — that array also contains the
        // plaintext password.
        'identifier_field' => 'email',

        'track' => [
            'login' => true,
            'login_failed' => true,
            'logout' => true,
            'authenticated' => false,
            'password_reset' => true,
            'email_verified' => true,
            'authentication_throttled' => true,
            'authorization_denied' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcast Monitoring
    |--------------------------------------------------------------------------
    |
    | Two independent things:
    |
    | 1. Activity tracking for queued broadcasts (Illuminate\Broadcasting\
    |    BroadcastEvent completing or failing) — always available, driver-
    |    agnostic, via the existing queue lifecycle.
    | 2. Live channel/connection statistics — ONLY available when the
    |    configured broadcasting driver exposes a management API. Currently
    |    supported: Pusher, and Laravel Reverb (which implements the Pusher
    |    HTTP protocol), both ONLY if pusher/pusher-php-server is installed.
    |    Every other driver (redis, log, null, ably, or Pusher/Reverb
    |    without the SDK installed) honestly reports "unavailable" rather
    |    than fabricating a channel list or connection count. See README §
    |    Broadcast Monitoring.
    |
    */
    'broadcast_monitoring' => [
        'enabled' => true,

        // Live connection/channel stats specifically (activity tracking for
        // queued broadcasts is unaffected by this toggle).
        'monitor_connections' => true,

        // Presence-channel member lists can reveal who is currently online
        // — disable if that's not appropriate for your application even
        // when the dashboard itself is authorized.
        'show_presence_members' => true,

        'auto_refresh' => true,

        // Milliseconds. Deliberately not "aggressive" — this polls a
        // third-party provider's API on every tick.
        'refresh_interval' => 10000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard UI
    |--------------------------------------------------------------------------
    |
    | The dashboard is entirely optional and OFF by installing the package —
    | it is opt-in via 'enabled' below. When disabled, no UI routes are
    | registered at all (not merely hidden behind a 403).
    |
    | 'authorize' controls whether the bundled `viewActivityTracker` Gate is
    | enforced. The Gate has no default opinion on WHO is allowed — see the
    | "Authorization" section of the README for how to define it in your
    | AuthServiceProvider. Leaving 'authorize' true with no Gate defined
    | denies everyone by default (fails closed, never open).
    |
    */
    'ui' => [
        'enabled' => env('ACTIVITY_TRACKER_UI_ENABLED', true),

        'prefix' => 'activity-tracker',

        'middleware' => ['web', 'auth'],

        'authorize' => true,

        'per_page' => 25,

        'per_page_options' => [25, 50, 100, 250],

        // 'light', 'dark', or 'system' (follows the OS/browser preference).
        'theme' => 'system',
    ],
];
