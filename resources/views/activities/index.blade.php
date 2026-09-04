<x-activity-tracker::layout title="Activities">
    <div
        data-at-activities-app
        data-at-index-url="{{ route('activity-tracker.activities.index') }}"
    >
        <x-activity-tracker::filter-panel
            :inputs="$inputs"
            :known-actions="$knownActions"
            :subject-type-options="$subjectTypeOptions"
            :http-methods="$httpMethods"
            :exception-class-options="$exceptionClassOptions"
            :execution-contexts="$executionContexts"
            :has-active-filters="$hasActiveFilters"
        />

        <div id="at-activities-results" data-at-results>
            @include('activity-tracker::activities._results')
        </div>
    </div>
</x-activity-tracker::layout>
