@props(['sortKey', 'label', 'currentSort', 'currentDirection'])
@php
    $isActive = $currentSort === $sortKey;
    $nextDirection = $isActive && $currentDirection === 'asc' ? 'desc' : 'asc';
    $query = array_merge(request()->query(), ['sort' => $sortKey, 'direction' => $nextDirection]);
@endphp
<th>
    <a href="{{ request()->url() }}?{{ http_build_query($query) }}">
        {{ $label }}
        @if ($isActive)
            @if ($currentDirection === 'asc')
                <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 15l-6-6-6 6"/></svg>
            @else
                <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
            @endif
        @endif
    </a>
</th>
