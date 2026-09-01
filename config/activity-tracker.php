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
    */
    'track' => [
        'created' => true,
        'updated' => true,
        'deleted' => true,
        'restored' => true,
        'force_deleted' => true,
        'retrieved' => true,
        'count' => true,
        'exists' => true,
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
