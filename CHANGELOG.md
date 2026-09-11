# Changelog

All notable changes to `abdulbaset/activity-tracker` will be documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - Unreleased

This release consolidates the latest development work that was previously split across
multiple unreleased `1.x` entries. All of the changes below are part of the upcoming
`3.0.0` release.

### Added

- Automatic, zero-code tracking of created/updated/deleted/restored/force-deleted models via the Eloquent wildcard event bus.
- Database query listener for sum/avg/min/max, bulk query-builder updates/deletes, and raw `DB::table()` operations.
- SQL query classifier with an extensible pattern API.
- Aggregated single-vs-collection retrieval tracking (`retrieved` / `retrieved_many`) via a request/job-scoped buffer, avoiding one row per activity.
- Generic causer resolution (`causer_type` / `causer_id`) that does not assume `App\Models\User`.
- Sensitive column stripping for old/new/changed values, with best-effort binding redaction.
- Recursion-safe storage layer (`TrackingContext::withoutTracking`).
- Optional asynchronous storage via a queued job.
- Queue worker lifecycle hooks to reset state between jobs.
- `activity:install`, `activity:clear`, `activity:prune` Artisan commands.
- Full config file, migration, and polymorphic `Activity` model with query scopes.
- Optional Blade-based admin dashboard: overview, searchable/filterable/sortable activities index, and a detailed per-activity view — served with zero-config CSS/JS (no publish step, no Node build) and fully removable via `ui.enabled`.
- Closed-by-default dashboard authorization via a `viewActivityTracker` Gate (local-only out of the box; host apps override it), plus an independent `ui.authorize` toggle and configurable `ui.middleware`.
- `ActivityTrackerFilters` and `ActivityTrackerStatisticsService` for reusable, injection-safe search/filter/sort/pagination and dashboard aggregate queries.
- Small, targeted engine additions to support the dashboard: `created` now captures `new_values`, `deleted`/`force_deleted` capture `old_values`, and `restored` carries its underlying diff instead of discarding it; activities also record an `execution_context` (`http`/`cli`/`queue`) in `metadata`.
- `ActivityLoggerInterface::logIntentionalView()` for deliberate audit-UI record views, decoupled from and never duplicating the automatic Eloquent listener.
- `id` added to the activities index's sortable-column whitelist.
- Asynchronous (`XMLHttpRequest`) activities table with debounced search, request abort/sequencing, shareable filtered URLs, Back/Forward support, non-blocking loading state, graceful retry state, and normal server-rendered fallback without JavaScript.
- Subtle CSS-transition animations for the filter panel, table rows, and toasts, respecting `prefers-reduced-motion: reduce`.
- Duration tracking (`duration_ms`) using `hrtime(true)` for Eloquent operations and Laravel's `QueryExecuted::$time` for aggregates/bulk/raw queries.
- Optional `memory_usage` and `memory_peak` tracking, plus `DurationFormatter` with Fast/Normal/Slow/Very Slow classifications.
- Full request URL as the primary location fact (`path` alongside `url`), with `route_name` retained as secondary metadata.
- `referrer_url` and `http_status` tracking, including response-time status backfilling through `ActivityTrackerRequestLifecycleMiddleware`.
- Sanitization and length truncation for `url` and `referrer_url`, including sensitive query-parameter redaction.
- `execution_context` promoted to an indexed database column, plus `command` and `database_connection` columns.
- Job context capture: `job_name`, `queue_name`, `queue_connection`, and `queue_attempt`.
- Automatic exception tracking through `ActivityTrackerExceptionHandlerDecorator`, recording dedicated `exception` activities with configurable stack traces and ignored exception types.
- Exception deduplication by exception-object identity without suppressing or replacing the application's original exception handling.
- New activity filters for `http_status`, `execution_context`, `exception_class`, and slow activities.
- Dedicated Exception section on the Activity Details page with collapsible/copyable stack trace.
- New additive observability migration with nullable columns so existing installations remain unaffected until migration is run.
- Authentication event tracking for `login`, `login_failed`, `logout`, `password_reset`, `email_verified`, `authentication_throttled`, and `authorization_denied`, with optional `authenticated` tracking.
- Authentication metadata columns: `auth_action`, `auth_guard`, `auth_provider`, and `auth_identifier`.
- Masked authentication identifiers; submitted passwords are never read.
- Broadcast monitoring for queued `ShouldBroadcast` events, recording broadcast event/channel/status and duration.
- `BroadcastChannelMonitorInterface` with Pusher/Reverb support and a Null fallback for unsupported drivers.
- Broadcast Monitoring dashboard with overview statistics, live channels, optional XHR auto-refresh, per-channel details, and presence members.
- Authentication dashboard with overview statistics, recent activity, and links into the filtered/AJAX activities index.
- New additive authentication and broadcast migration.
- `ActivityTrackerBroadcastStatisticsService::definedChannelPatterns()` for best-effort listing of registered `Broadcast::channel(...)` patterns.
- CI `composer validate --strict` job.
- Dedicated Composer package-discovery CI job using a real Laravel skeleton and Composer path repository.
- Regression tests for direct `User::find()` tracking, auth-provider retrieval exclusion, broadcast channel caching, Pusher Null fallback, and defined broadcast channel patterns.
- Regression tests for real Eloquent retrieval inside `TrackingContext::withoutTracking()` and exact identifier masking behavior.

### Changed

- Class naming standardized for at-a-glance identifiability. Core classes were renamed to their `ActivityTracker`-prefixed equivalents, including controllers, services, repository, manager, query classifier, observer, database listener, and retrieval flusher.
- The class naming changes are breaking for applications that directly bound or extended the old class names; route names, view names, and the config key remain unaffected.
- All package JavaScript now lives under a single `window.ActivityTracker` global.
- All package CSS is scoped under `.at-` classes and an `.at-scope` wrapper.
- Dashboard reads are explicitly wrapped in `TrackingContext::withoutTracking()` so the dashboard cannot generate tracking noise about itself.
- Authentication-model retrieval exclusion now happens at the actual Eloquent `retrieved` event and checks the call stack for genuine auth-provider resolution.
- Direct application retrievals such as `User::find($id)` are tracked normally, while framework-internal auth resolution remains excluded.
- Broadcast Monitoring live channel lists are memoized per request and cached across requests according to `broadcast_monitoring.cache_seconds`.
- `ActivityTrackerBroadcastTracker` channel/event extraction is resilient to Laravel internal queued-command shape changes.
- `maskIdentifier()` now uses a fixed-length mask that never reveals the trailing character or original identifier length.
- Removed `pusher/pusher-php-server` from `require-dev`; it remains an optional suggested dependency so the Null-monitor fallback can be tested correctly.

### Removed

- **`count` and `exists` tracking, entirely.** These operations produced no usable audit signal because Laravel's `QueryExecuted` event does not expose the actual result, and they generated high-volume, low-value activities.
- Notification Channel tracking (mail/database notification delivery) remains intentionally out of scope and is not implemented.
- `ShouldBroadcastNow` synchronous broadcast events are not tracked because there is no non-invasive hook for them.

### Fixed

- Opening the dashboard or any authenticated page no longer records a spurious `retrieved` activity for the logged-in user.
- The dashboard's internal Activity/subject/causer/statistics reads no longer generate tracking noise.
- Direct `User::find($id)` retrievals are correctly tracked while framework-internal auth-provider retrievals remain excluded.
- Opening the Activity Details page no longer creates spurious `retrieved`/`retrieved_many` activities for the displayed subject or causer.
- `TrackingContext::withoutTracking()` suppression is now checked before retrieval buffering, timing, or expected-query state is recorded.
- Authentication identifier masking no longer reveals the identifier's trailing character.
- Broadcast Monitoring no longer performs unnecessary repeated provider lookups during a single dashboard render.
- GitHub Actions Laravel compatibility matrix now pins `orchestra/testbench` instead of constraining only `illuminate/support`, preventing mismatched Illuminate package combinations.
- CI now verifies Composer metadata and Laravel package auto-discovery end to end.

### Security

- PHP stack-trace handling is documented because default trace formatting can include literal scalar call-chain arguments; `store_trace` can be disabled for high-sensitivity applications.
- Authentication failure/throttle identifier masking never falls back to the first submitted credential; only the explicitly configured identifier field is read because the credentials array may also contain the plaintext password.
- Sensitive URLs, referrers, model values, query bindings, and authentication identifiers are sanitized or masked according to the relevant configuration.

### Tests

- Added coverage for direct `User::find()` tracking versus `EloquentUserProvider::retrieveById()` exclusion.
- Added tests for broadcast channel-list memoization/caching and graceful fallback when the Pusher SDK is unavailable.
- Added tests for `definedChannelPatterns()` graceful degradation.
- Added a real request-lifecycle regression test for retrievals performed inside `TrackingContext::withoutTracking()`.
- Added exact `maskIdentifier()` tests for emails, usernames, very short values, and single-character values.
- Added CI validation and package auto-discovery installation tests.

### Known Limitations

- Several additional CI failures were reported through a screenshot whose text could not be fully and reliably extracted. The fixes above address every failure that could be root-caused with certainty from the available evidence; remaining failures require the raw CI log text for conclusive diagnosis.
- `ShouldBroadcastNow` events are intentionally not tracked.
- Notification Channel tracking is intentionally outside the scope of this release.

---

## [2.0.0] - 2025-01-13

### Added

- New `ActivityTrackerResource` for API responses.
- Comprehensive exception tracking with detailed configuration.
- Enhanced query logging with SQL, bindings, and execution time.
- Improved device detection using `jenssegers/agent`.
- Support for Laravel 10.
- Type hints and return types across all classes.
- Enum support for event types via `ActivityEventType`.
- Better configuration structure for authentication, models, and exceptions.

### Changed

- Improved configuration structure with nested settings.
- Enhanced model tracking with before/after state capture.
- Optimized database schema.
- Refactored service classes for better separation of concerns.
- Improved error handling and logging.
- Better handling of query parameters and headers.

### Fixed

- Fixed issues with model event tracking.
- Improved handling of null values in JSON columns.
- Better error handling in the activity logger.
- Fixed inconsistencies in event naming.
- Improved type safety throughout the package.

### Security

- Enhanced sensitive data filtering.
- Improved configuration for excluding sensitive model attributes.
- Better request-data sanitization.

### Dependencies

- Updated minimum PHP version requirement to 7.4.
- Added support for Laravel 9 and 10.
- Updated `jenssegers/agent` to version 2.6.

### Documentation

- Completely rewritten documentation.
- Added comprehensive configuration examples.
- Improved installation instructions.
- Added better examples for common use cases.

### Breaking Changes

- Removed helper functions in favor of the facade.
- Changed the database table name.
- Modified the database schema.
- Updated the configuration structure.

### Migration Guide from 1.x to 2.0

1. Run the new migration to update the table structure.
2. Some JSON columns have been optimized.
3. Publish the new configuration file:

```bash
php artisan vendor:publish --provider="Abdulbaset\ActivityTracker\Providers\ActivityTrackerServiceProvider"
```

4. Update the environment variables:

```env
ACTIVITY_TRACKER_ENABLED=true
ACTIVITY_TRACKER_LOG_METHOD=database
ACTIVITY_TRACKER_LOG_FILE_PATH=storage/logs/activity_tracker.log
```

---

## [1.0.0] - 2024-07-27

### Added

- Initial release of the Activity Tracker package for Laravel.
- Activity logging for model creation, updates, deletions, and retrievals.
- Additional activity context including user ID, IP address, device type, browser information, and other request details.
- Direct activity logging through the `ActivityTracker` facade.
- Support for logging custom events.
- `ActivityTrackerTrait` for tracking model activity directly from models.
- Observer-based automatic activity tracking.
- Support for custom observers.
- Configurable logging to database or file.
- Configurable activity log table and log file path.
- Configurable logging of model changes and authentication login/logout events.
- Migration support for creating and updating the activity log table.
- Support for logging old and new model values.
- Support for visited/retrieved events.
- Flexible integration through facade, trait, observers, or custom observers.
- Support for Laravel applications with configurable package settings.

### Documentation

- Installation and Composer usage documentation.
- Configuration examples.
- Migration commands for install, rollback, and refresh.
- Usage examples for direct facade logging, traits, observers, and custom observers.
- Examples for controller-based activity logging.
- Basic package feature and integration documentation.

---

## Versioning Notes

- `1.0.0` — Initial public release.
- `2.0.0` — Major redesign and feature expansion released on January 13, 2025.
- `3.0.0` — Upcoming major release consolidating the latest development work that was previously split into multiple unreleased entries.

[3.0.0]: https://github.com/AbdulbasetRS/Activity-Tracker/releases/tag/v3.0.0
[2.0.0]: https://github.com/AbdulbasetRS/Activity-Tracker/releases/tag/v2.0.0
[1.0.0]: https://github.com/AbdulbasetRS/Activity-Tracker/releases/tag/v1.0.0
