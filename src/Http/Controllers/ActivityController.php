<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Http\Controllers;

use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Services\ActivityFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

final class ActivityController extends Controller
{
    public function index(ActivityFilters $filters): View
    {
        return view('activity-tracker::activities.index', [
            'activities' => $filters->paginate(),
            'inputs' => $filters->inputs(),
            'hasActiveFilters' => $filters->hasActiveFilters(),
            'knownActions' => ActivityFilters::knownActions(),
            'httpMethods' => ActivityFilters::httpMethods(),
            'subjectTypeOptions' => $filters->subjectTypeOptions(),
            'perPageOptions' => (array) config('activity-tracker.ui.per_page_options', [25, 50, 100]),
        ]);
    }

    public function show(Activity $activity): View
    {
        return view('activity-tracker::activities.show', [
            'activity' => $activity,
            'subject' => $this->safeResolve(fn () => $activity->subject),
            'causer' => $this->safeResolve(fn () => $activity->causer),
        ]);
    }

    /**
     * Resolving a polymorphic relation can throw if the target class no
     * longer exists (e.g. a renamed/removed model) — the details page must
     * degrade gracefully ("no longer exists") rather than 500.
     */
    private function safeResolve(callable $resolver): mixed
    {
        try {
            return $resolver();
        } catch (\Throwable) {
            return null;
        }
    }
}
