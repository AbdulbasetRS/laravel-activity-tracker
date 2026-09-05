<x-activity-tracker::layout title="Broadcast Monitoring">
    <div
        data-at-broadcasts-app
        data-at-broadcasts-url="{{ route('activity-tracker.broadcasts') }}"
        data-at-auto-refresh="{{ $autoRefresh ? '1' : '0' }}"
        data-at-refresh-interval="{{ $refreshInterval }}"
    >
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-bottom:12px;">
            <label class="at-checkbox-item">
                <input type="checkbox" data-at-broadcasts-auto-refresh-toggle @checked($autoRefresh)>
                Auto refresh
            </label>
            <button type="button" class="at-btn" data-at-broadcasts-refresh>
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                Refresh
            </button>
        </div>

        <div id="at-broadcasts-results" data-at-results>
            @include('activity-tracker::broadcasts._overview')
        </div>
    </div>
</x-activity-tracker::layout>
