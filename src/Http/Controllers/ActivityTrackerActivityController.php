<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Http\Controllers;

use Abdulbaset\ActivityTracker\Contracts\ActivityLoggerInterface;
use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Services\ActivityTrackerFilters;
use Abdulbaset\ActivityTracker\Support\TrackingContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class ActivityTrackerActivityController extends Controller
{
    public function __construct(
        private readonly TrackingContext $trackingContext,
        private readonly ActivityLoggerInterface $tracker,
    ) {
    }

    public function index(Request $request, ActivityTrackerFilters $filters): View|JsonResponse
    {
        // Every query below reads the package's OWN Activity table to render
        // the package's OWN UI — it is never itself an application activity.
        // The Activity model is already globally excluded (see
        // ActivityTrackerManager::isExcludedByBaseRules), but the dashboard
        // wraps its own reads explicitly too, so that guarantee doesn't rely
        // on a single special case elsewhere in the engine.
        $data = $this->trackingContext->withoutTracking(fn () => [
            'activities' => $filters->paginate(),
            'inputs' => $filters->inputs(),
            'hasActiveFilters' => $filters->hasActiveFilters(),
            'knownActions' => ActivityTrackerFilters::knownActions(),
            'httpMethods' => ActivityTrackerFilters::httpMethods(),
            'subjectTypeOptions' => $filters->subjectTypeOptions(),
            'exceptionClassOptions' => $filters->exceptionClassOptions(),
            'executionContexts' => ActivityTrackerFilters::executionContexts(),
            'perPageOptions' => (array) config('activity-tracker.ui.per_page_options', [25, 50, 100]),
        ]);

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'html' => view('activity-tracker::activities._results', $data)->render(),
                    'total' => $data['activities']->total(),
                    'hasActiveFilters' => $data['hasActiveFilters'],
                ],
            ]);
        }

        return view('activity-tracker::activities.index', $data);
    }

    public function show(Activity $activity): View
    {
        // Loading the subject/causer here is purely so the DASHBOARD can
        // display them — it is not, itself, a meaningful application read,
        // so it must not silently create its own "retrieved" activity for
        // whatever model the subject/causer happen to be (see README §
        // Retrieval strategy / internal package reads).
        [$subject, $causer] = $this->trackingContext->withoutTracking(fn () => [
            $this->safeResolve(fn () => $activity->subject),
            $this->safeResolve(fn () => $activity->causer),
        ]);

        // Separately — and exactly once — record that an admin deliberately
        // viewed this subject through the audit UI. This is a real,
        // independent activity (not derived from the Eloquent hydration
        // above, which was suppressed), so it can never duplicate it.
        if ($subject !== null) {
            $this->tracker->logIntentionalView($subject, [
                'via' => 'activity_details',
                'activity_id' => $activity->id,
            ]);
        }

        return view('activity-tracker::activities.show', [
            'activity' => $activity,
            'subject' => $subject,
            'causer' => $causer,
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

    private function wantsJson(Request $request): bool
    {
        return $request->ajax() || $request->wantsJson() || $request->boolean('ajax');
    }
}
