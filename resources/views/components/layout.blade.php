@props(['title' => 'Dashboard'])
<!DOCTYPE html>
<html lang="en" data-at-default-theme="{{ config('activity-tracker.ui.theme', 'system') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · Activity Tracker</title>
    <link rel="stylesheet" href="{{ route('activity-tracker.assets', ['file' => 'css/app.css']) }}">
</head>
<body>
<div class="at-scope">
    <div class="at-shell">
        <aside class="at-sidebar" data-at-sidebar>
            <div class="at-sidebar-brand">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M3 3v18h18"/><path d="M18 9l-5 5-3-3-4 4"/></svg>
                Activity Tracker
            </div>
            <nav class="at-nav">
                <a href="{{ route('activity-tracker.dashboard') }}" class="at-nav-link {{ request()->routeIs('activity-tracker.dashboard') ? 'is-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                    Dashboard
                </a>
                <a href="{{ route('activity-tracker.activities.index') }}" class="at-nav-link {{ request()->routeIs('activity-tracker.activities.*') ? 'is-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    Activities
                </a>
                <a href="{{ route('activity-tracker.statistics') }}" class="at-nav-link {{ request()->routeIs('activity-tracker.statistics') ? 'is-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 15l4-6 3 3 5-7"/></svg>
                    Statistics
                </a>
                <a href="{{ route('activity-tracker.dashboard') }}#settings" class="at-nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.34 1.87l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.7 1.7 0 00-1.87-.34 1.7 1.7 0 00-1 1.55V21a2 2 0 11-4 0v-.09a1.7 1.7 0 00-1-1.55 1.7 1.7 0 00-1.87.34l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.7 1.7 0 00.34-1.87 1.7 1.7 0 00-1.55-1H3a2 2 0 110-4h.09a1.7 1.7 0 001.55-1 1.7 1.7 0 00-.34-1.87l-.06-.06a2 2 0 112.83-2.83l.06.06a1.7 1.7 0 001.87.34H9a1.7 1.7 0 001-1.55V3a2 2 0 114 0v.09a1.7 1.7 0 001 1.55 1.7 1.7 0 001.87-.34l.06-.06a2 2 0 112.83 2.83l-.06.06a1.7 1.7 0 00-.34 1.87V9a1.7 1.7 0 001.55 1H21a2 2 0 110 4h-.09a1.7 1.7 0 00-1.55 1z"/></svg>
                    Settings
                </a>
            </nav>
        </aside>

        <div class="at-main">
            <header class="at-topbar">
                <div style="display:flex;align-items:center;gap:12px;">
                    <button type="button" class="at-btn at-btn-icon at-sidebar-toggle" data-at-sidebar-toggle aria-label="Toggle navigation">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
                    </button>
                    <div class="at-topbar-title">{{ $title }}</div>
                </div>
                <div class="at-topbar-right">
                    <button type="button" class="at-btn at-btn-icon" data-at-theme-toggle aria-label="Toggle dark mode">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                    </button>
                    @auth
                        <span class="at-text-muted">{{ auth()->user()->name ?? auth()->user()->email ?? 'Signed in' }}</span>
                    @endauth
                </div>
            </header>

            <main class="at-content">
                {{ $slot }}
            </main>

            <footer class="at-footer">
                Laravel Activity Tracker &middot; v1.0.0
            </footer>
        </div>
    </div>
</div>
<script src="{{ route('activity-tracker.assets', ['file' => 'js/app.js']) }}" defer></script>
</body>
</html>
