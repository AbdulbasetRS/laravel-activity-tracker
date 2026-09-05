# Changelog

All notable changes to `abdulbaset/activity-tracker` will be documented here.

## [1.0.0] - Unreleased

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
- Optional Blade-based admin dashboard: overview, searchable/filterable/sortable
  activities index, and a detailed per-activity view — served with zero-config
  CSS/JS (no publish step, no Node build) and fully removable via `ui.enabled`.
- Closed-by-default dashboard authorization via a `viewActivityTracker` Gate
  (local-only out of the box; host apps override it), plus an independent
  `ui.authorize` toggle and configurable `ui.middleware`.
- `ActivityTrackerFilters` and `ActivityTrackerStatisticsService` for reusable, injection-safe
  search/filter/sort/pagination and dashboard aggregate queries.
- Small, targeted engine additions to support the dashboard: `created` now
  captures `new_values`, `deleted`/`force_deleted` capture `old_values`, and
  `restored` carries its underlying diff instead of discarding it; activities
  also record an `execution_context` (`http`/`cli`/`queue`) in `metadata`.

## [1.1.0] - Unreleased

### Removed
- **`count` and `exists` tracking, entirely.** They produced no usable audit
  signal (Laravel's `QueryExecuted` event never exposes the actual result),
  and were consistently the highest-volume, lowest-value activities the
  package generated. This is a hard rule in `ActivityTrackerManager`, not a
  config default — there is no toggle that brings them back.

### Fixed
- **Opening the dashboard (or any authenticated page) no longer records a
  spurious `retrieved` activity for the logged-in user.** Root cause:
  Laravel's own auth resolution (`auth` middleware, Gate checks,
  `auth()->user()`) retrieves the current guard's user via a plain Eloquent
  query on virtually every authenticated request; the package's global
  `eloquent.*` listener was recording that framework mechanic as if it were
  a meaningful application read. Every model configured under
  `auth.providers.*.model` is now excluded from `retrieved`/`retrieved_many`
  tracking by default (`retrieval.exclude_auth_models`).
- The dashboard's own internal reads (Activity rows for the table, subject/
  causer for display, statistics aggregates) are now explicitly wrapped in
  `TrackingContext::withoutTracking()` at every controller, on top of the
  existing Activity-model exclusion, so the dashboard can never generate
  tracking noise about itself.

### Added
- `ActivityLoggerInterface::logIntentionalView()` — records a deliberate
  "this record was viewed through the audit UI" activity, decoupled from
  (and never duplicating) the automatic Eloquent listener. The Activity
  Details page uses this for its subject exactly once per view, tagged
  `metadata.context = "ui"`. Toggle with `retrieval.track_ui_views`.
- `id` added to the activities index's sortable-column whitelist.
- Asynchronous (`XMLHttpRequest`) activities table: debounced search,
  request abort/sequencing to prevent stale-response race conditions,
  `history.pushState`/`replaceState` so filtered URLs are shareable and
  Back/Forward work, a non-blocking loading indicator, and a graceful
  "Unable to load activities — Retry" error state. Every control still
  works as a normal server-rendered page without JavaScript.
- Subtle CSS-transition animations for the filter panel, table rows, and
  toasts, fully minimized under `prefers-reduced-motion: reduce`.

### Changed
- **Class naming standardized for at-a-glance identifiability.** `ActivityController`,
  `ActivityDashboardController`, `ActivityStatisticsController`, `AssetController`,
  `ActivityFilters`, `ActivityStatisticsService`, `ActivityRepository`, the core
  `ActivityTracker` service, `QueryClassifier`, `EloquentActivityObserver`,
  `DatabaseQueryListener`, and `RetrievalFlusher` were renamed to their
  `ActivityTracker`-prefixed equivalents (e.g. `ActivityTrackerActivityController`,
  `ActivityTrackerManager`). `Models\Activity` and other narrowly-scoped
  internal classes intentionally kept their names — see
  [Class naming conventions](README.md#class-naming-conventions) for the full
  table and reasoning. **This is a breaking change** for anyone who bound or
  extended the old class names directly; route names, view names, and the
  config key were already consistent and are unaffected.
- All package JavaScript now lives under a single `window.ActivityTracker`
  global; all CSS is scoped under `.at-` classes and a `.at-scope` wrapper.

## [1.2.0] - Unreleased

### Added
- **Duration tracking** (`duration_ms`): `hrtime(true)`-based timing around
  Eloquent create/update/delete/restore/force-delete's underlying query, and
  Laravel's own `QueryExecuted::$time` for aggregates/bulk/raw queries.
  Configurable via `performance.*`; optional `memory_usage`/`memory_peak`
  (off by default). New `DurationFormatter` for display + Fast/Normal/Slow/
  Very Slow classification.
- **Full request URL as the primary location fact** (`path` added
  alongside the existing `url`; `route_name` remains secondary metadata),
  plus `referrer_url` (the HTTP `Referer` header) and `http_status`
  (backfilled after the response is sent by the new
  `ActivityTrackerRequestLifecycleMiddleware`, pushed onto Laravel's global
  middleware stack). Both `url` and `referrer_url` are sanitized
  (`sensitive_query_parameters` redaction) and length-truncated before
  storage.
- **`execution_context` promoted to a real, indexed column**
  (`http`/`cli`/`queue`), plus new `command` (CLI signature name) and
  `database_connection` columns.
- **Job context capture** (`job_name`, `queue_name`, `queue_connection`,
  `queue_attempt`) from the `JobProcessing` event, reset between jobs like
  the rest of `TrackingContext` — no leakage across a worker's job queue.
- **Automatic exception tracking.** `ActivityTrackerExceptionHandlerDecorator`
  observes (via `Container::extend()`, never replacing) the application's
  bound `ExceptionHandler`. Recorded as a dedicated `exception` action —
  class, message, file, line, and a configurable, length-limited stack
  trace — with a default `ignored_exceptions` list (validation/auth/404/
  throttle) to avoid flooding the log with routine "expected" exceptions.
  Deduplicated by exception-object identity; a tracker failure can never
  suppress or replace the original exception handling.
- New Activities-table columns (URL, Status, Duration) and filters
  (`http_status`, `execution_context`, `exception_class`, "slow activities
  only"), a dedicated Exception section on the Activity Details page with a
  collapsible, copyable stack trace, and `id`/`duration_ms`/`http_status`
  added to the sortable-column whitelist.
- New additive migration (`add_observability_columns_to_activities_table`)
  — every new column is nullable; existing rows and installations are
  unaffected until you run `php artisan migrate` again.

### Security
- Documented that PHP's default stack-trace formatting can include literal
  scalar call-chain arguments (see README § Exception tracking); `store_trace`
  can be disabled for high-sensitivity applications while still keeping
  class/message/file/line.

## [1.3.0] - Unreleased

### Added
- **Authentication event tracking**: `login`, `login_failed`, `logout`,
  `password_reset`, `email_verified`, `authentication_throttled`, and
  `authorization_denied` (via `Gate::after()`), plus optional `authenticated`
  (off by default — see README). New `ActivityTrackerAuthenticationTracker`
  listener; new `auth_action`/`auth_guard`/`auth_provider`/`auth_identifier`
  columns. Identifiers are always masked (`ahmed@example.com` ->
  `a***@example.com`) before storage; the submitted password is never read.
  `password_changed`, `password_reset_requested`, `account_locked`, and
  `account_unlocked` were deliberately NOT implemented — no reliable core
  Laravel event exists for them (documented in README rather than faked).
- **Broadcast monitoring**: observes queued `ShouldBroadcast` events
  (`Illuminate\Broadcasting\BroadcastEvent`) completing or failing via the
  existing queue lifecycle, recording a `broadcast` activity per channel
  (`broadcast_event`/`broadcast_channel`/`broadcast_channel_type`/
  `broadcast_status`, plus duration). New
  `BroadcastChannelMonitorInterface` abstraction with `Pusher`/`Reverb`
  (via the optional `pusher/pusher-php-server` SDK) and a `Null` fallback
  for every other driver, which honestly reports "unavailable" rather than
  fabricating channel lists or connection counts — a channel with an
  unknown connection count is `null`, never `0`.
  `ShouldBroadcastNow` (synchronous) events are NOT tracked — documented
  limitation, no non-invasive hook exists for them.
- New Broadcast Monitoring dashboard (overview stats, live channels table
  with optional XHR auto-refresh, per-channel detail with presence members)
  and Authentication dashboard (overview stats + recent activity, linking
  into the existing filtered/AJAX activities index).
- New additive migration (`add_auth_and_broadcast_columns_to_activities_table`).
- Explicitly confirmed NOT implemented: Notification Channel tracking
  (mail/database notification delivery) — out of scope by design, distinct
  from Broadcasting.

### Security
- Login-failure/throttle identifier masking never falls back to "the first
  submitted credential" — only the explicitly configured identifier field
  is ever read, because that credentials array also contains the plaintext
  password.
