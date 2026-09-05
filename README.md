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
8. [Retrieval strategy & internal reads](#retrieval-strategy--internal-reads)
9. [Retrieval tracking](#retrieval-tracking)
10. [Bulk operations](#bulk-operations)
11. [Query tracking](#query-tracking)
12. [Duration & performance](#duration--performance)
13. [Full URL, path & referrer](#full-url-path--referrer)
14. [Exception tracking](#exception-tracking)
15. [Authentication event tracking](#authentication-event-tracking)
16. [Broadcast monitoring](#broadcast-monitoring)
17. [Sensitive data protection](#sensitive-data-protection)
18. [Ignoring models](#ignoring-models)
19. [Authentication / causer tracking](#authentication--causer-tracking)
20. [Request metadata](#request-metadata)
21. [Queue support](#queue-support)
22. [Transactions](#transactions)
23. [Reading activities](#reading-activities)
24. [Admin dashboard](#admin-dashboard)
25. [Class naming conventions](#class-naming-conventions)
26. [Events](#events)
27. [Extending the package](#extending-the-package)
28. [Performance considerations](#performance-considerations)
29. [Limitations](#limitations)
30. [Troubleshooting](#troubleshooting)
31. [Testing](#testing)
32. [Contributing](#contributing)
33. [License](#license)

---

## What it does

The package hooks into two layers of your application automatically:

- **Eloquent lifecycle events** (`eloquent.*`) for semantically rich
  operations: created, updated (with a diff), deleted, restored,
  force-deleted, and retrieved.
- **The database query listener** (`DB::listen`) for everything Eloquent
  events cannot see: `sum()`/`avg()`/`min()`/`max()`, mass query-builder
  updates/deletes, and raw `DB::table()` operations.

`count()` and `exists()` are deliberately **never** tracked — see
[Tracked operations](#tracked-operations) for why.

A correlation mechanism ensures a single logical operation — e.g.
`$user->update([...])`, which issues both an Eloquent `updating`/`updated`
pair and an `UPDATE` SQL statement — produces **one** activity, not several.
The same principle extends to the package's own internal reads: rendering
the dashboard itself is never mistaken for an application activity — see
[Retrieval strategy & internal reads](#retrieval-strategy--internal-reads).

## Why it exists

Most audit-log packages require you to add a trait to every model you want
to track, or to manually call a logging method. That works, but it means:

- New models are silently untracked until someone remembers to add the trait.
- Bulk/raw operations that bypass model events are invisible.
- A naive "track every Eloquent retrieval" approach produces enormous noise —
  including from Laravel's own internals (see below).

This package instead observes the framework's own event system, so coverage
is automatic and consistent across the whole application, today and for
every model added in the future — while actively filtering out framework
and package-internal noise rather than recording it blindly.

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
| `action` | `created`, `updated`, `deleted`, `restored`, `force_deleted`, `retrieved`, `retrieved_many`, `sum`/`avg`/`min`/`max`, `bulk_updated`, `bulk_deleted`, `raw_insert`, `exception` |
| `subject_type` / `subject_id` | Polymorphic — what it happened to |
| `old_values` / `new_values` / `changed_values` | JSON diffs for updates |
| `query` / `query_type` / `database_connection` | Captured SQL for query-listener-sourced activities |
| `result_count` | Row count, where derivable (see [Limitations](#limitations)) |
| `duration_ms` / `memory_usage` / `memory_peak` | See [Duration & performance](#duration--performance) |
| `ip_address`, `user_agent`, `route_name`, `http_method`, `url`, `path`, `referrer_url`, `http_status` | HTTP context, null outside HTTP — see [Full URL, path & referrer](#full-url-path--referrer) |
| `execution_context` | `http`, `cli`, or `queue` |
| `command` | The Artisan command's signature name, in CLI context |
| `job_name`, `queue_name`, `queue_connection`, `queue_attempt` | Captured automatically when tracking happens inside a queued job — see [Queue support](#queue-support) |
| `exception_class`, `exception_message`, `exception_file`, `exception_line`, `stack_trace` | See [Exception tracking](#exception-tracking) |
| `auth_action`, `auth_guard`, `auth_provider`, `auth_identifier` | See [Authentication event tracking](#authentication-event-tracking) — `auth_identifier` is always pre-masked |
| `broadcast_event`, `broadcast_channel`, `broadcast_channel_type`, `broadcast_status` | See [Broadcast monitoring](#broadcast-monitoring) |
| `metadata` | Free-form JSON for anything else |

Every column added after the initial release is nullable — upgrading and
running the new migration never touches or invalidates existing rows; they
simply have `null` for metadata that didn't exist yet when they were
recorded.

Publish and customize it if needed:

```bash
php artisan vendor:publish --tag=activity-tracker-migrations
```

## Tracked operations

| Operation | Source | Action recorded |
|---|---|---|
| `Model::create()` | Eloquent event | `created` (with the created attributes) |
| `$model->update()` / `save()` | Eloquent event | `updated` (with diff) |
| `$model->delete()` | Eloquent event | `deleted` (with the values at time of deletion) |
| `$model->restore()` | Eloquent event | `restored` (with its diff) |
| `$model->forceDelete()` | Eloquent event | `force_deleted` (with the values at time of deletion) |
| `Model::find()` / `first()` / `firstWhere()` | Eloquent event (buffered) | `retrieved` |
| `Model::get()` / `all()` / `cursor()` | Eloquent event (buffered) | `retrieved_many` |
| `sum()` / `avg()` / `min()` / `max()` | Query listener | `sum` / `avg` / `min` / `max` |
| `Model::where(...)->update([...])` | Query listener | `bulk_updated` |
| `Model::where(...)->delete()` | Query listener | `bulk_deleted` |
| `DB::table(...)->insert()/update()/delete()` | Query listener | `raw_insert` / `bulk_updated` / `bulk_deleted` |
| Viewing a record's subject on the Activity Details page | Explicit, opt-in (`logIntentionalView()`) | `retrieved` (tagged `metadata.context = "ui"`) |
| An unhandled/reported exception | Exception handler decorator | `exception` — see [Exception tracking](#exception-tracking) |
| Login / failed login / logout / password reset / email verified / throttled | Laravel auth events | `login` / `login_failed` / `logout` / `password_reset` / `email_verified` / `authentication_throttled` — see [Authentication event tracking](#authentication-event-tracking) |
| Authorization check denied | `Gate::after()` | `authorization_denied` |
| A queued broadcast completing or failing | Queue lifecycle (`BroadcastEvent` job) | `broadcast` — see [Broadcast monitoring](#broadcast-monitoring) |

Toggle any of these independently under `track` in the config file.

### `count()` and `exists()` are never tracked

Earlier versions tracked `count()` and `exists()` via the query listener.
They have been **removed entirely** — not hidden from the UI, not disabled
by default, genuinely removed from the tracking logic. Reasoning:

- Laravel's `QueryExecuted` event never exposes the actual count/boolean
  result (see [Limitations](#limitations)), so these activities could only
  ever record "a count/exists query ran against this table", which carries
  essentially no audit value on its own.
- In practice they were among the highest-volume, lowest-signal activities
  the package produced — most applications run far more `count()`/`exists()`
  calls than meaningful writes.

This is enforced as a hard rule in `ActivityTrackerManager`, independent of
configuration — there is no toggle that brings them back.

## Retrieval strategy & internal reads

"Retrieved" tracking is the part of this package most likely to surprise you
if left unmanaged, because Eloquent fires a `retrieved` event for **every**
model hydration — including ones your application code never asked for.
Two exclusions keep it meaningful:

**1. Laravel's own auth resolution is excluded by default.** Every request
through the `auth` middleware, every `Gate`/`can` check, and every call to
`auth()->user()` resolves the current guard's user via a plain Eloquent
query (`Illuminate\Auth\EloquentUserProvider::retrieveById()`). That is a
framework mechanic that happens on nearly every authenticated page load in
your entire application, not a meaningful business read — so any model
configured under `auth.providers.*.model` (across all guards) is excluded
from `retrieved`/`retrieved_many` tracking:

```php
'retrieval' => [
    // ...
    'exclude_auth_models' => true, // set false to audit login reads too
],
```

**2. The dashboard's own reads are excluded.** Every controller the package
ships wraps its internal queries — loading Activities for the table, a
subject/causer for display, statistics aggregates — in
`TrackingContext::withoutTracking()`:

```php
app(\Abdulbaset\ActivityTracker\Support\TrackingContext::class)->withoutTracking(function () {
    // Anything tracked inside here is suppressed, then automatically
    // restored afterward — even if the callback throws.
    $user = User::find($id);
});
```

This is nestable, exception-safe, and the exact mechanism the package uses
internally to guarantee **opening `/activity-tracker` never creates activity
noise for itself** (see [Admin dashboard](#admin-dashboard)). Use it in your
own code for any read that shouldn't count as a business event.

**3. Intentional UI views are a separate, explicit mechanism — never
inferred from Eloquent hydration.** The Activity Details page calls:

```php
app(\Abdulbaset\ActivityTracker\Contracts\ActivityLoggerInterface::class)
    ->logIntentionalView($subjectModel, ['via' => 'activity_details']);
```

exactly once per page view, recording a real `retrieved` activity tagged
`metadata.context = "ui"`. This is deliberately decoupled from the automatic
listener (which is suppressed for that same read via `withoutTracking()`
above), so it can never duplicate it — and it is *not* subject to the
auth-model exclusion, because a deliberate "an admin viewed this record
through the audit UI" event is exactly the kind of thing worth auditing,
even for a model that would otherwise be filtered as auth noise. Toggle it
with `retrieval.track_ui_views`.

**Honest limitation:** a blind Eloquent `retrieved` event carries no
information about *why* the read happened. The two exclusions above cover
the overwhelmingly common sources of noise (framework auth resolution and
the package's own UI), but an application-level read that your own code
performs for a reason you don't consider meaningful (e.g. a middleware you
wrote, a policy check) will still be tracked like any other retrieval unless
you wrap it in `withoutTracking()` yourself. There is no reliable, generic
way to infer "was this read meaningful?" from the Eloquent event alone.

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

A dedicated `ActivityTrackerQueryClassifier` normalizes SQL (case,
whitespace, quoting, identifier backticks) before classifying it as
`select`, `insert`, `update`, `delete`, `count`, `exists`, `sum`, `avg`,
`min`, `max`, or `unknown`. Plain `select` queries are **not** logged
directly — they're already represented by `retrieved`/`retrieved_many`, and
logging both would duplicate every read. `count` and `exists` are
classified (the information is genuinely there in the SQL shape) but are
**never** turned into an activity — see
[Tracked operations](#tracked-operations).

The classifier is extensible without forking the package:

```php
app(\Abdulbaset\ActivityTracker\Contracts\QueryClassifierInterface::class)
    ->extendPattern('/^explain/', 'diagnostic');
```

## Duration & performance

Every automatically-tracked activity records `duration_ms` — a high-resolution
duration in milliseconds, measured as tightly as possible around the actual
tracked operation, never the whole HTTP request:

- **Create/update/delete/restore/force-delete**: measured with `hrtime(true)`
  between the matching Eloquent pre-hook (`creating`/`updating`/`deleting`/
  `restoring`) and post-hook — essentially the underlying database write.
- **Aggregates, bulk updates/deletes, raw queries**: use Laravel's own
  `QueryExecuted::$time` directly — it's already a precise millisecond
  duration for that exact statement, so there's no need (or benefit) to
  hand-roll a second timer.
- **`retrieved`/`retrieved_many`**: intentionally `null`. A buffered
  collection retrieval has no single meaningful duration to report — see
  [Retrieval tracking](#retrieval-tracking).
- **Intentional UI views**: `null` — not a measured operation at all.

```php
'performance' => [
    'enabled' => true,
    'track_duration' => true,

    // memory_get_usage()/memory_get_peak_usage() add a small but real cost
    // to every tracked operation — off by default.
    'track_memory' => false,

    'slow_ms' => 100,
    'very_slow_ms' => 1000,
],
```

`slow_ms`/`very_slow_ms` only drive the dashboard's Fast/Normal/Slow/Very
Slow classification (`Abdulbaset\ActivityTracker\Support\DurationFormatter`)
and the "Slow activities only" filter — they never change what gets
tracked. The dashboard formats durations intelligently (`0.42 ms`,
`845.20 ms`, `1.42 s`) rather than showing raw floats.

This package is an audit/observability tool, not a profiler — duration and
(optional) memory figures exist to flag genuinely slow operations, not to
replace Blackfire, Telescope, or a real APM.

## Full URL, path & referrer

The **full request URL** — not the route name — is the primary "where did
this happen" fact, because a route can be renamed, unnamed, or a closure,
while the URL is simply what actually happened:

```
url:        https://example.com/admin/users/15?tab=permissions
path:       admin/users/15
route_name: admin.users.show   (secondary metadata, still recorded)
```

The HTTP `Referer` header (yes, misspelled in the HTTP spec itself) is
captured as `referrer_url` when present, and left `null` — never
fabricated — when absent. Both `url` and `referrer_url`:

- have configured **sensitive query parameters redacted** before storage
  (`token`, `password`, `api_key`, `secret`, `access_token`, `refresh_token`,
  `client_secret`, `signature`, by default — extend via
  `sensitive_query_parameters`): `?token=abc123` becomes `?token=[REDACTED]`,
  preserving the rest of the URL;
- are **truncated** at a configurable length (`context.max_url_length`,
  `context.max_referrer_length` — both default 2048) since both are
  untrusted, attacker-influenceable input;
- are **escaped on output** everywhere the dashboard renders them (standard
  Blade `{{ }}` escaping) — never rendered as raw HTML, never executed.

### HTTP status code

Most activities are recorded *mid-request*, before a response — and
therefore a status code — exists yet. `http_status` is backfilled once,
after the response is actually sent, by
`ActivityTrackerRequestLifecycleMiddleware::terminate()` (pushed onto
Laravel's global middleware stack, so it covers API-only routes too, not
just the "web" group): a single `UPDATE ... WHERE request_id = ?` per
request, skipped entirely for requests that tracked nothing at all.
Exceptions that carry their own status code (e.g. a thrown `HttpException`)
report it immediately; everything else gets the real response status a
moment later.

## Exception tracking

Unhandled/reported exceptions are recorded as a dedicated `exception`
activity — never disguised as a CRUD action, always visually distinct in
the dashboard (a dedicated red badge, its own section on the details page).

### How it hooks into Laravel

`ActivityTrackerExceptionHandlerDecorator` wraps (via
`Container::extend()`) whatever `Illuminate\Contracts\Debug\ExceptionHandler`
is already bound — your own custom `Handler`, or Laravel's default. It is
never a replacement:

- `report()`, `shouldReport()`, `render()`, and `renderForConsole()` are all
  forwarded to the original handler, unchanged — your own custom
  `render()`/`register()` logic keeps working exactly as before.
- Recording an exception is wrapped in its own `try`/`catch` inside
  `ActivityTrackerExceptionService`: if building or storing the activity
  fails for any reason, the *original* exception still reaches
  `$handler->report($e)` normally. A tracker failure can never replace or
  suppress your application's real error handling.
- The same exception instance is never recorded twice — deduplicated by
  object identity (`spl_object_id()`), not by message or trace content
  (which two unrelated exceptions could share).

### What's captured

`exception_class`, `exception_message`, `exception_file`, `exception_line`,
and (configurable) `stack_trace`, plus the same request context every other
activity gets (`url`, `execution_context`, `request_id`, causer, ...) and an
`http_status` derived immediately when the exception itself carries one
(`Symfony\...\HttpExceptionInterface`), or backfilled like any other
activity otherwise.

### Ignored ("expected") exceptions

Without a filter, **every** failed login attempt and unmatched route would
create an exception activity — the same noise problem that led to removing
`count()`/`exists()` tracking. These are excluded by default:

```php
'exceptions' => [
    'enabled' => true,
    'store_trace' => true,
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
```

Add or remove classes freely (subclasses are matched via `instanceof`).

### Stack trace security — read this before enabling in production

`store_trace` is `true` by default and `getTraceAsString()` output is
truncated to `max_trace_length` (10,000 characters) — but **PHP's default
stack trace formatting can include literal scalar arguments** passed to
functions in the call chain. If a plain-text password or token was ever
passed as a bare string argument somewhere in that call chain, it can show
up in the trace. This is standard PHP/Laravel behavior, not something a
single trace *string* can be selectively redacted from after the fact.
For high-sensitivity applications, set `'store_trace' => false` — the
class/message/file/line are still fully captured either way.

## Authentication event tracking

Login/logout/account-security events are observed via Laravel's own
authentication events — never overriding auth behavior, and a tracking
failure can never break a real login/logout (every handler in
`ActivityTrackerAuthenticationTracker` is wrapped in its own `try`/`catch`).

| Event | Action | Source |
|---|---|---|
| Successful login | `login` | `Illuminate\Auth\Events\Login` |
| Failed attempt | `login_failed` | `Illuminate\Auth\Events\Failed` |
| Logout | `logout` | `Illuminate\Auth\Events\Logout` |
| Session/token re-authentication | `authenticated` | `Illuminate\Auth\Events\Authenticated` — **off by default**, see below |
| Password reset completed | `password_reset` | `Illuminate\Auth\Events\PasswordReset` |
| Email verified | `email_verified` | `Illuminate\Auth\Events\Verified` |
| Too many attempts | `authentication_throttled` | `Illuminate\Auth\Events\Lockout` |
| Authorization check denied | `authorization_denied` | `Gate::after()` |

Each works across guards — `auth_guard`/`auth_provider` are captured from
the event itself (or resolved from `auth.guards.{guard}.provider`), never
assuming the default `"web"` guard.

### Why `authenticated` defaults to off

`Authenticated` fires on essentially **every** authenticated request —
session/token resolution, not an actual login action — enabling it by
default would reproduce the exact "retrieved User" noise problem this
package already fixed once (see
[Retrieval strategy & internal reads](#retrieval-strategy--internal-reads)).
Turn it on deliberately if you want that level of detail:

```php
'authentication' => ['track' => ['authenticated' => true]],
```

### What's intentionally NOT implemented

Only events Laravel's core authentication system reliably fires are
implemented — nothing is faked:

- **`password_changed`** — a password change is just a `User` update; it's
  already covered (with the password itself already stripped) by ordinary
  CRUD tracking. There's no separate core Laravel event for it.
- **`password_reset_requested`** — core Laravel's `Password::sendResetLink()`
  dispatches no event to hook.
- **`account_locked` / `account_unlocked`** — core Laravel has no concept of
  a *permanent* account lock, only the *temporary* throttling `Lockout`
  represents (`authentication_throttled`). Claiming a permanent lock from a
  temporary throttle would be misleading, so it isn't done.

### Authorization denials

Registered via `Gate::after()` — Laravel's own documented mechanism for
observing every authorization check's *outcome* without altering it. Only
**denials** are recorded (an allowed check is not a security-relevant
signal); the ability name is stored in `metadata.ability`, and the checked
subject (if a model) becomes the activity's `subject_type`/`subject_id`.

### Security

The submitted password is **never** read, logged, or stored — not even
masked. On a failed attempt, only the configured identifier field
(`authentication.identifier_field`, default `email`) is extracted, and only
that exact field — there is deliberately no "fall back to the first
credential" behavior, because that array also contains the plaintext
password. The identifier is always masked before storage:

```
ahmed@example.com  ->  a***@example.com
ahmed123           ->  a***3
```

## Broadcast monitoring

Monitors Laravel Broadcasting — WebSocket channels and their connected
clients — **not** Notification Channels (mail/database/Slack notification
delivery). Two independent things are provided:

1. **Broadcast activity tracking** — always available, driver-agnostic:
   observes `Illuminate\Broadcasting\BroadcastEvent` (the queued job Laravel
   creates for any `ShouldBroadcast` event) completing or failing, via the
   existing queue lifecycle hooks. Recorded as a `broadcast` activity per
   channel, with `broadcast_event`, `broadcast_channel`,
   `broadcast_channel_type`, `broadcast_status` (`sent`/`failed`), and
   `duration_ms`.
2. **Live channel/connection statistics** — **only** available when the
   configured broadcasting driver exposes a management API. Currently
   integrated: **Pusher**, and **Laravel Reverb** (which implements the
   Pusher HTTP protocol), and only when the optional
   `pusher/pusher-php-server` package is installed. Every other driver
   (`redis`, `log`, `null`, `ably`, or Pusher/Reverb without the SDK)
   reports honestly:

   ```
   Live connection statistics unavailable for the configured broadcasting driver (redis).
   ```

   **Connection counts are never fabricated.** A channel with an unknown
   connection count shows `—`, never `0` — those mean different things.

### "Sent" is not "received"

`broadcast_status = sent` means the queued broadcast job completed without
throwing — the application/provider *accepted* the operation. It does
**not** mean any connected browser actually received or rendered it.
Laravel has no built-in client-acknowledgement mechanism to observe that,
and this package does not pretend otherwise.

### `ShouldBroadcastNow` is not tracked

Only queued (`ShouldBroadcast`) events are observed, via the queue job
Laravel wraps them in. `ShouldBroadcastNow` events broadcast synchronously
with no queue-job hook to observe non-invasively, so they are **not**
tracked in this release — a documented limitation, not a silent gap.

### Presence channels

Presence channel members (when the provider integration supports it) are
available on the channel detail page — `user_id` and display name only,
never credentials, tokens, or session data. Disable member visibility
independently of the rest of the dashboard:

```php
'broadcast_monitoring' => ['show_presence_members' => false],
```

### Configuration

```php
'broadcast_monitoring' => [
    'enabled' => true,
    'monitor_connections' => true,
    'show_presence_members' => true,
    'auto_refresh' => true,
    'refresh_interval' => 10000, // ms — deliberately not aggressive; this polls a third-party API
],
```

### Provider outages never break your application

Every call to the broadcasting provider's management API
(`ActivityTrackerBroadcastChannelMonitor`'s Pusher/Reverb adapter) is
wrapped — an outage, timeout, or credential problem degrades to
"unavailable", never an exception that reaches your users.

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

### Job context

When any tracked operation happens while a queued job is processing,
`execution_context` is `"queue"` and these columns are captured directly
from the job Laravel handed to `JobProcessing`:

```
job_name:         App\Jobs\SyncUserOrders   (the queued job's class)
queue_name:       default
queue_connection: redis
queue_attempt:    1
```

Like everything else, this is reset between jobs — no job's context can
leak into the next job's activities in the same worker process.

Note: the `sync` queue connection (the Laravel default when nothing else is
configured) never fires `JobProcessing`/`JobProcessed` at all — jobs run
inline without going through a worker — so `execution_context` for a
`sync`-dispatched job's tracked operations will be whatever it already was
(typically `"http"`, since `sync` jobs usually run mid-request). This is a
Laravel behavior, not a package limitation.

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
| `activity-tracker.authentication` | Login/logout/security overview |
| `activity-tracker.broadcasts` | Broadcast Monitoring overview |
| `activity-tracker.broadcasts.channel` | A single channel's detail page |
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
HTTP method, route, request ID, batch ID) with a live "N active" indicator,
and column sorting — including by `id`, `created_at`, `action`,
`subject_type`, `subject_id`, and `causer`. Sortable columns and per-page
sizes are whitelisted server-side
(`Abdulbaset\ActivityTracker\Services\ActivityTrackerFilters`) — raw
query-string values never reach `orderBy()` directly; an unrecognized `sort`
value silently falls back to `created_at`. Filters persist across
pagination automatically since they're plain query-string parameters.

The activity detail page links directly into batch/request-scoped views of
this same index (`?batch_id=...` / `?request_id=...`) so you can see
everything that happened in one HTTP request or one correlated operation.

### AJAX behavior

The activities index loads and updates via `XMLHttpRequest` — search,
every filter, sorting, pagination, and the per-page selector all update the
table in place, with no full page reload:

- **Search is debounced** (400ms) while typing; pressing Enter or clicking
  "Search" submits immediately.
- **Requests are sequenced**, not just fired — starting a new request aborts
  any request still in flight, so a fast typist can never have an older
  response overwrite a newer one.
- **The URL reflects filter state** via `history.pushState()` (filter/search
  changes) or `history.replaceState()` (pagination, sorting — kept out of
  back-button history to avoid clutter), so the browser Back/Forward buttons
  and copy/pasting the URL both work, and `popstate` re-fetches the correct
  page without pushing a duplicate history entry.
- **Loading state** is a small spinner badge over the (dimmed, still
  visible) existing table — never a full-screen loader — and the previous
  results stay on screen until the new ones are ready.
- **Errors** replace the results area with "Unable to load activities.
  Please try again." and a Retry button; no raw exception ever reaches the
  browser.
- **Graceful fallback**: every control is a real `<a>`/`<form>` with a real
  `href`/`action` first. JavaScript intercepts the click/submit for the AJAX
  behavior above; with JavaScript disabled, every one of these still works
  as an ordinary server-rendered page navigation to the same named route.

The endpoint is the same named route as the page itself
(`activity-tracker.activities.index`) — Laravel's `$request->ajax()`
(driven by the `X-Requested-With: XMLHttpRequest` header the browser sets
automatically for `XMLHttpRequest`) is what selects the JSON response:

```json
{
    "success": true,
    "data": {
        "html": "<div class=\"at-table-wrap\">...</div>",
        "total": 8421,
        "hasActiveFilters": true
    }
}
```

No URL is ever hardcoded in JavaScript — the results container carries the
index route's URL in a `data-at-index-url` attribute, and every link the JS
intercepts already has its own real `href` generated by `route(...)` in
Blade.

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
Animations (row refresh, filter panel open/close, toasts) are subtle and
CSS-transition-based, and are minimized automatically when the visitor's OS
has `prefers-reduced-motion: reduce` set — functionality is unaffected.

### JavaScript and CSS isolation

All package JavaScript lives under a single global, `window.ActivityTracker`
— nothing else is added to `window`. All package CSS classes are prefixed
`.at-` (`.at-card`, `.at-table`, `.at-btn`, ...) and every page is wrapped in
a single `.at-scope` container, so the dashboard cannot collide with or
override your host application's own Bootstrap, Tailwind, or hand-rolled
`.card`/`.table`/`.button`/`.modal` classes elsewhere on the same domain.

## Class naming conventions

Every package-specific class is named so it's identifiable at a glance in a
stack trace or log line — not because of any real PHP namespace collision
risk (there isn't one), but because "ActivityController" or "ActivityService"
read as generic enough to belong to almost any application:

| Role | Class |
|---|---|
| Central tracking decision-maker | `Services\ActivityTrackerManager` |
| Activities index/detail controller | `Http\Controllers\ActivityTrackerActivityController` |
| Dashboard overview controller | `Http\Controllers\ActivityTrackerDashboardController` |
| Statistics page controller | `Http\Controllers\ActivityTrackerStatisticsController` |
| Dashboard CSS/JS controller | `Http\Controllers\ActivityTrackerAssetController` |
| Search/filter/sort/pagination | `Services\ActivityTrackerFilters` |
| Dashboard aggregate queries | `Services\ActivityTrackerStatisticsService` |
| Activity storage backend | `Services\ActivityTrackerRepository` |
| SQL classification | `Services\ActivityTrackerQueryClassifier` |
| Eloquent `eloquent.*` listener | `Observers\ActivityTrackerObserver` |
| `QueryExecuted` listener | `Listeners\ActivityTrackerQueryListener` |
| Retrieval buffer → activity flush | `Services\ActivityTrackerRetrievalFlusher` |
| Exception handler decorator | `Handling\ActivityTrackerExceptionHandlerDecorator` |
| Exception recording policy (ignore list, dedup) | `Services\ActivityTrackerExceptionService` |
| Auth event listener (`Login`, `Failed`, `Gate::after()`, ...) | `Listeners\ActivityTrackerAuthenticationTracker` |
| Broadcast job observer | `Listeners\ActivityTrackerBroadcastTracker` |
| Broadcast dashboard controller | `Http\Controllers\ActivityTrackerBroadcastController` |
| Authentication dashboard controller | `Http\Controllers\ActivityTrackerAuthenticationController` |
| Live channel/connection stats (per-provider) | `Contracts\BroadcastChannelMonitorInterface`, `Services\Broadcasting\PusherBroadcastChannelMonitor`, `Services\Broadcasting\NullBroadcastChannelMonitor` |
| Broadcast dashboard aggregate queries | `Services\ActivityTrackerBroadcastStatisticsService` |

A few classes deliberately kept their shorter names, on the judgment that
prefixing them would add noise without adding clarity:

- **`Models\Activity`** — this is the package's public API (`Activity::query()->...`,
  documented extensively above); renaming it would be a breaking change for
  no real disambiguation benefit inside a package literally about "activity"
  tracking.
- **`Support\TrackingContext`, `CauserResolver`, `RequestContextResolver`,
  `Services\SensitiveDataSanitizer`, `Services\ActivityTransformer`** —
  narrowly-scoped internal collaborators, already unambiguous in context, never
  referenced directly by application code.
- **Contracts (`ActivityLoggerInterface`, `QueryClassifierInterface`, ...),
  Events, the queue `Jobs\StoreActivity` job, and `Console` commands** — already
  clear from their namespace and behavior-based names.

Route names (`activity-tracker.*`), the view namespace
(`activity-tracker::...`), and the config key (`activity-tracker`) were
already consistent before this pass and remain so.

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
  timing — not the query's return value or affected-row count.
  `sum`/`avg`/`min`/`max`, `bulk_updated`, and `bulk_deleted` activities
  record *that* the operation happened and against which table, but the
  numeric result itself is not captured (this is also *why* `count()` and
  `exists()` were removed entirely rather than tracked with a
  perpetually-null result — see [Tracked operations](#tracked-operations)).
  `retrieved`/`retrieved_many` are the exception — their `result_count` is
  fully accurate because it comes from Eloquent's own hydration, not the
  query listener.
- **A blind Eloquent `retrieved` event carries no information about intent.**
  The package excludes the two overwhelmingly common sources of false-signal
  noise (Laravel's own auth resolution, and the package's own dashboard
  reads) — see
  [Retrieval strategy & internal reads](#retrieval-strategy--internal-reads)
  — but cannot generically know that some other read your own application
  code performs isn't meaningful to you. Wrap it in
  `TrackingContext::withoutTracking()` yourself if so.
- **Raw/table-only queries cannot be mapped to an Eloquent model class.**
  `DB::table('users')->sum('balance')` produces `model_type = null, table =
  users` by design — the package will not guess which model, if any,
  represents a table.
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
- **`http_status` backfill is best-effort, not instantaneous.** It updates
  after the response is sent, scoped by `request_id`, via a terminable
  middleware. A process that terminates abnormally (a fatal error the
  handler never sees, `exit()` called mid-request, a killed worker) can
  leave `http_status` `null` for activities from that request — this is
  strictly better than guessing a status that was never actually reached.
- **Stack traces can contain scalar arguments from the call chain** — see
  [Exception tracking § Stack trace security](#exception-tracking) before
  enabling `store_trace` for a high-sensitivity application.

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
first/get, aggregates, bulk update/delete, raw `DB::table()` operations,
ignored models, sensitive-field exclusion, recursion safety, and retrieval
buffering/flushing; explicit regressions proving `count()`/`exists()` create
zero activities even if force-enabled via config; the auth-model exclusion
(and that it can be disabled), `TrackingContext::withoutTracking()`'s
nesting and exception-safety, and the intentional-UI-view mechanism recording exactly
once; duration recording (present, numeric, positive, disableable, and
correctly absent for retrieved/retrieved_many); full URL/query-string/path/
route capture over real HTTP requests, sensitive-query-parameter redaction
on both `url` and `referrer_url`, referrer truncation, and `http_status`
backfill after the response is sent; and the exception subsystem —
class/message/file/line/trace capture, trace truncation and disabling, the
default ignored-exception list (and that it's configurable), object-identity
deduplication of a re-reported exception, status-code derivation from
`HttpExceptionInterface`, and that disabling exception tracking never stops
the *original* handler from still running.
Also covered: the intentional-UI-view mechanism recording exactly
once with no duplicate from the suppressed automatic listener; job/queue
context capture (`job_name`/`queue_name`/`queue_connection`/`queue_attempt`)
and that it never leaks between two different jobs in the same worker;
**authentication tracking** — login (with guard/causer/duration), failed
login (masked identifier, and that the submitted password never appears
anywhere in the stored row, not even in `metadata`), logout (causer captured
before the guard forgets it), that `authenticated` is off by default and
can be enabled, password reset, email verification, throttling (masked
identifier), authorization denial via `Gate::after()` (and that an
*allowed* check is never recorded), and that the whole subsystem can be
disabled; and **broadcast monitoring** — the default `NullBroadcastChannelMonitor`
honestly reporting unavailability (never a fabricated `0`), a processed
`BroadcastEvent` job producing one activity per channel with the correct
channel type, a failed job recording the exception, duration measurement,
non-broadcast jobs being ignored entirely, the feature being disableable,
and a corrupt/unreadable job payload never propagating an exception into
the queue worker — plus, for the dashboard:
the dashboard: access control (default Gate, custom Gate, `authorize`
toggle), the UI being fully removable (`ui.enabled = false` deregisters its
routes), search, every filter, ID/column sorting with a malicious-input
whitelist test, pagination, the AJAX JSON endpoint, batch/request-scoped
views, graceful handling of a deleted subject or causer on the detail page,
and end-to-end proof that visiting every dashboard page creates no activity
beyond the one deliberate subject view.

## Contributing

Issues and pull requests are welcome. Please run `composer stan` and
`composer format` before submitting, and add tests for new behavior.

## License

MIT. See [LICENSE](LICENSE).
