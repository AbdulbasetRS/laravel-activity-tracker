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
        'sum' => 'warning',
        'avg' => 'warning',
        'min' => 'warning',
        'max' => 'warning',
        'bulk_updated' => 'info',
        'bulk_deleted' => 'danger',
        'raw_insert' => 'success',
        'exception' => 'danger',
        'login' => 'success',
        'login_failed' => 'danger',
        'logout' => 'neutral',
        'authenticated' => 'neutral',
        'password_reset' => 'info',
        'email_verified' => 'success',
        'authentication_throttled' => 'warning',
        'authorization_denied' => 'danger',
        'broadcast' => 'info',
    ];

    $icons = [
        'created' => '<path d="M12 5v14M5 12h14"/>',
        'updated' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/>',
        'deleted' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/>',
        'restored' => '<path d="M3 12a9 9 0 109-9"/><path d="M3 3v6h6"/>',
        'force_deleted' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 10l6 6M15 10l-6 6"/>',
        'retrieved' => '<circle cx="12" cy="12" r="3"/><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/>',
        'retrieved_many' => '<circle cx="12" cy="12" r="3"/><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/>',
        'sum' => '<path d="M4 4h16l-6 8 6 8H4l6-8z"/>',
        'avg' => '<path d="M4 4h16l-6 8 6 8H4l6-8z"/>',
        'min' => '<path d="M4 4h16l-6 8 6 8H4l6-8z"/>',
        'max' => '<path d="M4 4h16l-6 8 6 8H4l6-8z"/>',
        'bulk_updated' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/>',
        'bulk_deleted' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/>',
        'raw_insert' => '<path d="M12 5v14M5 12h14"/>',
        'exception' => '<path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>',
        'login' => '<path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>',
        'login_failed' => '<path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><path d="M3 12h9"/><path d="M9 8l4 4-4 4"/>',
        'logout' => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'authenticated' => '<path d="M20 6L9 17l-5-5"/>',
        'password_reset' => '<rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>',
        'email_verified' => '<path d="M4 4h16v16H4z"/><path d="M22 6l-10 7L2 6"/>',
        'authentication_throttled' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
        'authorization_denied' => '<circle cx="12" cy="12" r="9"/><path d="M4.9 4.9l14.2 14.2"/>',
        'broadcast' => '<path d="M4.9 19.1a10 10 0 010-14.2M19.1 4.9a10 10 0 010 14.2M7.8 16.2a5 5 0 010-8.4M16.2 7.8a5 5 0 010 8.4"/><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/>',
    ];

    $variant = $variants[$action] ?? 'neutral';
    $icon = $icons[$action] ?? '<circle cx="12" cy="12" r="9"/>';
    $label = str_replace('_', ' ', $action);
@endphp
<span class="at-badge at-badge-{{ $variant }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">{!! $icon !!}</svg>
    {{ $label }}
</span>
