@props(['action'])
@php
    $variants = [
        'created' => 'success',
        'updated' => 'info',
        'deleted' => 'danger',
        'restored' => 'success',
        'force_deleted' => 'danger',
        'retrieved' => 'neutral',
        'retrieved_many' => 'neutral',
        'count' => 'warning',
        'exists' => 'warning',
        'sum' => 'warning',
        'avg' => 'warning',
        'min' => 'warning',
        'max' => 'warning',
        'bulk_updated' => 'info',
        'bulk_deleted' => 'danger',
        'raw_insert' => 'success',
    ];

    $icons = [
        'created' => '<path d="M12 5v14M5 12h14"/>',
        'updated' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/>',
        'deleted' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/>',
        'restored' => '<path d="M3 12a9 9 0 109-9"/><path d="M3 3v6h6"/>',
        'force_deleted' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 10l6 6M15 10l-6 6"/>',
        'retrieved' => '<circle cx="12" cy="12" r="3"/><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/>',
        'retrieved_many' => '<circle cx="12" cy="12" r="3"/><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/>',
        'count' => '<path d="M4 20V10M12 20V4M20 20v-7"/>',
        'exists' => '<path d="M20 6L9 17l-5-5"/>',
        'sum' => '<path d="M4 4h16l-6 8 6 8H4l6-8z"/>',
        'avg' => '<path d="M4 4h16l-6 8 6 8H4l6-8z"/>',
        'min' => '<path d="M4 4h16l-6 8 6 8H4l6-8z"/>',
        'max' => '<path d="M4 4h16l-6 8 6 8H4l6-8z"/>',
        'bulk_updated' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/>',
        'bulk_deleted' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/>',
        'raw_insert' => '<path d="M12 5v14M5 12h14"/>',
    ];

    $variant = $variants[$action] ?? 'neutral';
    $icon = $icons[$action] ?? '<circle cx="12" cy="12" r="9"/>';
    $label = str_replace('_', ' ', $action);
@endphp
<span class="at-badge at-badge-{{ $variant }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">{!! $icon !!}</svg>
    {{ $label }}
</span>
