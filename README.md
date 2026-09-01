# Activity Tracker for Laravel

*[اقرأ هذا الملف بالعامية المصرية](README.ar.md)*

Automatic, zero-code database activity/audit tracking for Laravel applications.

`abdulbaset/activity-tracker` observes your application's Eloquent models and
database queries and turns them into normalized activity records — without
you adding a trait, an observer, or a single line of code to your Models,
Controllers, Services, or Repositories.

```bash
composer require abdulbaset/activity-tracker
php artisan migrate
```

That's it. Tracking begins immediately.

---

## Table of contents

1. [What it does](#what-it-does)
2. [Why it exists](#why-it-exists)
3. [Installation](#installation)
4. [Automatic activation](#automatic-activation)
5. [Configuration](#configuration)
6. [Database migration](#database-migration)
7. [Tracked operations](#tracked-operations)
8. [Retrieval tracking](#retrieval-tracking)
9. [Bulk operations](#bulk-operations)
10. [Query tracking](#query-tracking)
11. [Sensitive data protection](#sensitive-data-protection)
12. [Ignoring models](#ignoring-models)
13. [Authentication / causer tracking](#authentication--causer-tracking)
14. [Request metadata](#request-metadata)
15. [Queue support](#queue-support)
16. [Transactions](#transactions)
17. [Reading activities](#reading-activities)
18. [Admin dashboard](#admin-dashboard)
19. [Events](#events)
20. [Extending the package](#extending-the-package)
21. [Performance considerations](#performance-considerations)
22. [Limitations](#limitations)
23. [Troubleshooting](#troubleshooting)
24. [Testing](#testing)
25. [Contributing](#contributing)
26. [License](#license)

---

## What it does

The package hooks into two layers of your application automatically:

- **Eloquent lifecycle events** (`eloquent.*`) for semantically rich
  operations: created, updated (with a diff), deleted, restored,
  force-deleted, and retrieved.
- **The database query listener** (`DB::listen`) for everything Eloquent
  events cannot see: `count()`, `exists()`, `sum()`/`avg()`/`min()`/`max()`,
  mass query-builder updates/deletes, and raw `DB::table()` operations.

A correlation mechanism ensures a single logical operation — e.g.
`$user->update([...])`, which issues both an Eloquent `updating`/`updated`
pair and an `UPDATE` SQL statement — produces **one** activity, not several.

## Why it exists

Most audit-log packages require you to add a trait to every model you want
to track, or to manually call a logging method. That works, but it means:

- New models are silently untracked until someone remembers to add the trait.
- Bulk/raw operations that bypass model events are invisible.
- Aggregate reads (`count()`, `exists()`) are never captured at all.

This package instead observes the framework's own event system, so coverage
is automatic and consistent across the whole application, today and for
every model added in the future.

## Installation

```bash
composer require abdulbaset/activity-tracker
```

Laravel's package auto-discovery registers `ActivityTrackerServiceProvider`
automatically. Then run the bundled migration:

```bash
php artisan migrate
```

Or use the convenience installer, which also offers to publish the config
and migration for customization:

```bash
php artisan activity:install
```

## Automatic activation

No code changes are required anywhere in your application. The package:

- Registers itself via Laravel's package discovery (`composer.json`'s
  `extra.laravel.providers`).
- Listens to the `eloquent.*` wildcard event and `QueryExecuted`, both of
  which Laravel fires natively for every model and every query.
- Ships its migration inside the package itself (loaded via
  `loadMigrationsFrom`), so `php artisan migrate` works even if you never
  publish anything.

If you disable package discovery, register the provider manually:

```php
// config/app.php
'providers' => [
    Abdulbaset\ActivityTracker\ActivityTrackerServiceProvider::class,
],
```

## Configuration

Publish the config file to customize behavior:

```bash
php artisan vendor:publish --tag=activity-tracker-config
```

This produces `config/activity-tracker.php` with every option documented
inline: the master enable switch, connection/table, which operations to
track, retrieval-tracking behavior, sensitive columns, ignore lists, query
logging, request-context capture, queue settings, and retention.

## Database migration

The `activities` table stores:

| Column | Purpose |
|---|---|
| `batch_id` / `request_id` | Correlate activities from the same request/job |
| `causer_type` / `causer_id` | Polymorphic — who did it |
| `action` | `created`, `updated`, `deleted`, `restored`, `force_deleted`, `retrieved`, `retrieved_many`, `count`, `exists`, `sum`/`avg`/`min`/`max`, `bulk_updated`, `bulk_deleted`, `raw_insert` |
| `subject_type` / `subject_id` | Polymorphic — what it happened to |
| `old_values` / `new_values` / `changed_values` | JSON diffs for updates |
| `query` / `query_type` | Captured SQL for query-listener-sourced activities |
| `result_count` | Row count, where derivable (see [Limitations](#limitations)) |
| `ip_address`, `user_agent`, `route_name`, `http_method`, `url` | HTTP context, null outside HTTP |
| `metadata` | Free-form JSON for anything else |

Publish and customize it if needed:

```bash
php artisan vendor:publish --tag=activity-tracker-migrations
```

## Tracked operations

| Operation | Source | Action recorded |
|---|---|---|
| `Model::create()` | Eloquent event | `created` |
| `$model->update()` / `save()` | Eloquent event | `updated` (with diff) |
| `$model->delete()` | Eloquent event | `deleted` |
| `$model->restore()` | Eloquent event | `restored` |
| `$model->forceDelete()` | Eloquent event | `force_deleted` |
| `Model::find()` / `first()` / `firstWhere()` | Eloquent event (buffered) | `retrieved` |
| `Model::get()` / `all()` / `cursor()` | Eloquent event (buffered) | `retrieved_many` |
| `Model::count()` / `where(...)->count()` | Query listener | `count` |
| `Model::exists()` | Query listener | `exists` |
| `sum()` / `avg()` / `min()` / `max()` | Query listener | `sum` / `avg` / `min` / `max` |
| `Model::where(...)->update([...])` | Query listener | `bulk_updated` |
| `Model::where(...)->delete()` | Query listener | `bulk_deleted` |
| `DB::table(...)->insert()/update()/delete()` | Query listener | `raw_insert` / `bulk_updated` / `bulk_deleted` |

Toggle any of these independently under `track` in the config file.

## Retrieval tracking

`Model::find()`, `first()`, `firstWhere()`, and similar single-result calls
produce exactly **one** `retrieved` activity.

`Model::get()`, `all()`, or any call that hydrates a collection does **not**
produce one activity per row. Instead, retrievals are buffered for the
duration of the current request/console command/queue job and flushed as a
**single** `retrieved_many` activity with `result_count` set to the number of
models hydrated — whether that's 3 or 300,000.

```php
'retrieval' => [
    'track_single' => true,
    'track_many' => true,
    'store_ids' => false, // opt-in: store the retrieved IDs in `metadata`
    'max_ids' => 100,     // cap even when store_ids is enabled
],
```

Storing IDs for large collections is opt-in and capped for exactly the
reason the config comment says: memory and storage overhead.

## Bulk operations

```php
User::where('status', 'inactive')->update(['status' => 'active']);
User::where('status', 'inactive')->delete();
```

Both bypass per-model Eloquent events entirely (this is standard Eloquent
behavior, not a tracker limitation). The query listener detects the
resulting `UPDATE`/`DELETE` SQL and records `bulk_updated` / `bulk_deleted`
against the table, without loading the affected models into memory.

## Query tracking

A dedicated `QueryClassifier` normalizes SQL (case, whitespace, quoting,
identifier backticks) before classifying it as `select`, `insert`, `update`,
`delete`, `count`, `exists`, `sum`, `avg`, `min`, `max`, or `unknown`. Plain
`select` queries are **not** logged directly — they're already represented
by `retrieved`/`retrieved_many`, and logging both would duplicate every read.

The classifier is extensible without forking the package:

```php
app(\Abdulbaset\ActivityTracker\Contracts\QueryClassifierInterface::class)
    ->extendPattern('/^explain/', 'diagnostic');
```

## Sensitive data protection

Configured `sensitive_columns` (password, tokens, secrets, etc.) are
**excluded entirely** from `old_values`/`new_values`/`changed_values` — never
masked, never stored in any form:

```php
'sensitive_columns' => [
    'password', 'password_confirmation', 'remember_token',
    'api_token', 'access_token', 'refresh_token', 'secret',
],
```

Raw SQL bindings are not stored by default (`query_log.store_bindings =
false`) precisely because bindings have no reliable column names to match
against `sensitive_columns`. If you opt in, a best-effort heuristic redacts
long, token-shaped values — see [Limitations](#limitations).

## Ignoring models

```php
'ignored_models' => [
    App\Models\TemporaryLog::class,
],

'ignored_tables' => [
    'migrations', 'jobs', 'sessions', // ...and more by default
],
```

The package's own `activities` table (and `Activity` model) is always
excluded — this is what prevents infinite recursion, on top of the explicit
`TrackingContext::withoutTracking()` guard used when writing activity rows.

## Authentication / causer tracking

The package never assumes `App\Models\User` is your authenticatable model.
It asks Laravel's auth factory for whatever guard is active and stores a
polymorphic reference:

```php
$activity->causer_type; // e.g. App\Models\Admin, App\Models\User, null
$activity->causer_id;
$activity->causer;      // resolved via morphTo()
```

Outside of an authenticated context (guests, CLI, queue jobs with no auth
session), both fields are `null`.

## Request metadata

When running inside an HTTP request, activities capture `ip_address`,
`user_agent`, `route_name`, `http_method`, and `url` (each independently
toggleable under `context` in the config). None of this is required —
every accessor degrades to `null` in CLI, Artisan commands, and queue jobs,
which the package is fully functional in.

## Queue support

Enable asynchronous storage so tracking never adds latency to the request
that triggered it:

```php
'queue' => [
    'enabled' => true,
    'connection' => null, // defaults to your app's default queue connection
    'queue' => 'default',
],
```

The queued job payload is a plain, JSON-safe array (IDs and scalars) — never
a serialized Eloquent model — so it never depends on the original request or
database state still being current by the time a worker picks it up.

Long-running workers are handled explicitly: `TrackingContext` (which holds
the current batch ID, request ID, and buffered retrievals) is reset on every
`JobProcessing` event and flushed + reset again on `JobProcessed`, so nothing
leaks from one job to the next in the same worker process.

## Transactions

```php
DB::transaction(function () use ($user) {
    $user->update(['status' => 'active']);
});
```

If synchronous storage is used and the transaction commits, the activity is
already written by the time the transaction completes. If the transaction
**rolls back**, the model's `updated` Eloquent event never actually fires
for a failed `UPDATE` in the first place in most rollback scenarios — but if
your queue mode is enabled, a job could still be dispatched before a
rollback occurs. To guarantee an activity is never recorded for work that
gets rolled back, dispatch the queued job only after the transaction commits
by leaving `queue.enabled` off for transactional code paths, or by
wrapping the operation and manually deferring via `DB::afterCommit()` in
your own application code. This trade-off is documented rather than hidden:
implementing fully transaction-aware buffering for every possible queue
configuration was judged more complex than the benefit justified for a v1.

## Reading activities

```php
use Abdulbaset\ActivityTracker\Models\Activity;

Activity::query()
    ->where('action', 'updated')
    ->latest()
    ->get();

Activity::causedBy($user)
    ->forSubject($post)
    ->whereAction('updated')
    ->latest()
    ->get();

Activity::inBatch($batchId)->get();
```

None of the automatic tracking depends on this API — it exists purely for
querying what has already been recorded.

## Admin dashboard

A complete, optional Blade-based admin dashboard ships with the package —
dashboard overview, a searchable/filterable/sortable activities table, and a
detailed per-activity view. No React/Vue/Inertia/Livewire, no Node build
step, no separate publish step required to get working, styled pages.

```
/activity-tracker              -> dashboard overview
/activity-tracker/activities   -> searchable, filterable, sortable index
/activity-tracker/activities/1 -> full detail view for one activity
/activity-tracker/statistics   -> breakdowns and a lightweight time-series chart
```

### Enabling / disabling

The dashboard is on by default. Turn it off entirely — no routes are
registered at all, not merely hidden behind a 403 — with:

```php
// config/activity-tracker.php
'ui' => [
    'enabled' => false,
],
```

### Routes

Every route is named, under the `activity-tracker.` prefix:

| Name | Purpose |
|---|---|
| `activity-tracker.dashboard` | Overview page |
| `activity-tracker.activities.index` | Searchable/filterable activities table |
| `activity-tracker.activities.show` | Single activity detail |
| `activity-tracker.statistics` | Breakdown + chart page |
| `activity-tracker.assets` | Serves the dashboard's own CSS/JS |

Never hardcode the dashboard's URL — always use `route('activity-tracker.activities.index')`
and friends, so a custom `ui.prefix` doesn't break your links.

### Authorization

The dashboard is never exposed just because a user is logged in. Access is
gated by a Laravel `Gate` named `viewActivityTracker`, checked via the `can:`
middleware whenever `ui.authorize` is `true` (the default).

**The package ships a safe, closed-by-default fallback**: the bundled Gate
only allows access when `app()->environment('local')` — i.e. it works out of
the box on your local machine and denies everyone once deployed, until you
explicitly decide otherwise. This mirrors how Laravel Telescope and Horizon
behave, and deliberately does **not** assume a `User` model, a role column,
or an `isAdmin()` method exists anywhere in your application.

To control access yourself, define the Gate in your own `AuthServiceProvider`
(this overrides the package's default because the package boots first):

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewActivityTracker', function ($user) {
    return $user->isAdmin(); // or ->hasRole('admin'), ->can('view-audit-log'), etc.
});
```

To drop the Gate check entirely (e.g. you already fully control access via
`ui.middleware`), set:

```php
'ui' => [
    'authorize' => false,
],
```

`ui.middleware` (default `['web', 'auth']`) is applied to every dashboard
route except the static asset route, which intentionally has no auth
requirement — it serves only CSS/JS, nothing user-specific or sensitive.

### Configuration

```php
'ui' => [
    'enabled' => true,
    'prefix' => 'activity-tracker',
    'middleware' => ['web', 'auth'],
    'authorize' => true,
    'per_page' => 25,
    'per_page_options' => [25, 50, 100, 250],
    'theme' => 'system', // 'light', 'dark', or 'system'
],
```

### Search, filters, sorting, pagination

The activities index supports a single search box (description, action,
subject type/ID, causer ID, IP, route, request ID, batch ID), an expandable
filter panel (action multi-select, subject type, causer, date range, IP,
HTTP method, route, request ID, batch ID), and column sorting. Sortable
columns and per-page sizes are whitelisted server-side
(`Abdulbaset\ActivityTracker\Services\ActivityFilters`) — raw query-string
values never reach `orderBy()` directly. Filters persist across pagination
automatically since they're plain query-string parameters.

The activity detail page links directly into batch/request-scoped views of
this same index (`?batch_id=...` / `?request_id=...`) so you can see
everything that happened in one HTTP request or one correlated operation.

### Customizing views

```bash
php artisan vendor:publish --tag=activity-tracker-views
```

Publishes to `resources/views/vendor/activity-tracker/`. Anything there
overrides the package's own view of the same relative path — standard
Laravel package view override behavior.

### Customizing assets

```bash
php artisan vendor:publish --tag=activity-tracker-assets
```

Publishes to `public/vendor/activity-tracker/{css,js}/app.css|app.js`. If a
published copy exists, the dashboard's asset route (`activity-tracker.assets`)
serves that instead of the package's bundled copy — edit it freely.

### Dark mode

A theme toggle in the top bar switches between light and dark, persisted in
`localStorage` (no database setting involved). `ui.theme` controls the
initial preference for first-time visitors: `'light'`, `'dark'`, or
`'system'` (follows the OS/browser preference via `prefers-color-scheme`).

## Events

```php
use Abdulbaset\ActivityTracker\Events\ActivityRecording; // before persistence, payload is mutable
use Abdulbaset\ActivityTracker\Events\ActivityRecorded;  // after persistence
```

```php
Event::listen(ActivityRecording::class, function (ActivityRecording $event) {
    $event->payload['metadata']['tenant_id'] = tenant()->id;
});
```

## Extending the package

Every major component is bound against an interface and can be swapped via
the container:

```php
$this->app->bind(
    \Abdulbaset\ActivityTracker\Contracts\ActivityStorageInterface::class,
    \App\Support\ElasticsearchActivityStorage::class,
);
```

Available contracts: `ActivityLoggerInterface`, `QueryClassifierInterface`,
`ActivityTransformerInterface`, `SensitiveDataSanitizerInterface`,
`ActivityStorageInterface`.

## Performance considerations

- Plain `SELECT` queries are never logged individually — only the aggregated
  retrieval count is recorded, once per request/job.
- Collection retrieval is O(1) activity records regardless of row count.
- Config is merged once at boot and read through Laravel's config repository
  (already in-memory/cached in production via `config:cache`).
- Bulk operations never load the affected models into memory.
- Async storage (`queue.enabled`) removes the write from the request's
  critical path entirely.

## Limitations

Please read this section before relying on the package for compliance-grade
auditing:

- **`result_count` for query-listener-sourced activities is not always
  available.** Laravel's `QueryExecuted` event exposes SQL, bindings, and
  timing — not the query's return value or affected-row count. `count`,
  `exists`, `sum`/`avg`/`min`/`max`, `bulk_updated`, and `bulk_deleted`
  activities record *that* the operation happened and against which table,
  but the numeric result itself is not captured. `retrieved`/`retrieved_many`
  are the exception — their `result_count` is fully accurate because it
  comes from Eloquent's own hydration, not the query listener.
- **Raw/table-only queries cannot be mapped to an Eloquent model class.**
  `DB::table('users')->count()` produces `model_type = null, table = users`
  by design — the package will not guess which model, if any, represents a
  table.
- **A query-builder mass update/delete cannot be distinguished from an
  equivalent raw `DB::table()` call.** Both produce identical SQL. Both are
  recorded as `bulk_updated`/`bulk_deleted` against the table.
- **Bindings are not stored by default**, and when enabled, sensitive-value
  redaction in bindings is a best-effort heuristic (long, whitespace-free
  strings), not a guarantee — there is no reliable column name to check
  against `sensitive_columns` at the binding level.
- **Transactional rollback is not fully guaranteed to prevent a queued
  activity write** — see [Transactions](#transactions).
- **The dashboard cannot display what the engine didn't capture.** The
  activity detail page shows "Not captured" for aggregate results and
  affected-row counts rather than a fabricated number, for the reasons
  above. It also never guesses a link to your application's own model-show
  route — if you want that, add it in a published, customized view.

## Troubleshooting

**No activities are being recorded at all.**
Check `activity-tracker.enabled` and that the `activities` table exists
(`php artisan migrate`). Also confirm the model/table isn't in
`ignored_models` / `ignored_tables`.

**I see duplicate-looking activities for one `save()` call.**
This should not happen — please open an issue with the model's traits and
the exact call, as it likely indicates a gap in the creating/updating/
deleting expectation correlation.

**Activities aren't queued even though `queue.enabled` is true.**
Confirm a queue worker is running (`php artisan queue:work`) and that
`queue.connection` resolves to a valid, running connection.

**I get a 403 visiting the dashboard.**
Expected outside the `local` environment until you define your own
`viewActivityTracker` Gate — see [Authorization](#authorization).

**I get redirected to a login route I don't have, instead of a 403.**
That's Laravel's `auth` middleware (in `ui.middleware`), not this package —
it redirects guests to your `login` route. Log in first, or adjust
`ui.middleware` if you're gating access another way.

**The dashboard is unstyled / plain HTML.**
Check that the `activity-tracker.assets` route is reachable (it's excluded
from `ui.middleware`'s auth requirement on purpose) and that nothing in your
app is intercepting `/activity-tracker/assets/*`.

## Testing

```bash
composer install
composer test      # PHPUnit via Orchestra Testbench
composer stan       # PHPStan
composer format     # Laravel Pint
```

The test suite covers: create/update/delete/restore/force-delete, find/
first/get, count/exists/aggregates, bulk update/delete, raw `DB::table()`
operations, ignored models, sensitive-field exclusion, recursion safety, and
retrieval buffering/flushing — plus, for the dashboard: access control
(default Gate, custom Gate, `authorize` toggle), the UI being fully
removable (`ui.enabled = false` deregisters its routes), search, every
filter, sorting, pagination, batch/request-scoped views, and graceful
handling of a deleted subject or causer on the detail page.

## Contributing

Issues and pull requests are welcome. Please run `composer stan` and
`composer format` before submitting, and add tests for new behavior.

## License

MIT. See [LICENSE](LICENSE).
