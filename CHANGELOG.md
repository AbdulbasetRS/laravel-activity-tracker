# Changelog

All notable changes to `abdulbaset/activity-tracker` will be documented here.

## [1.0.0] - Unreleased

### Added
- Automatic, zero-code tracking of created/updated/deleted/restored/force-deleted models via the Eloquent wildcard event bus.
- Database query listener for count/exists/sum/avg/min/max, bulk query-builder updates/deletes, and raw `DB::table()` operations.
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
- `ActivityFilters` and `ActivityStatisticsService` for reusable, injection-safe
  search/filter/sort/pagination and dashboard aggregate queries.
- Small, targeted engine additions to support the dashboard: `created` now
  captures `new_values`, `deleted`/`force_deleted` capture `old_values`, and
  `restored` carries its underlying diff instead of discarding it; activities
  also record an `execution_context` (`http`/`cli`/`queue`) in `metadata`.
