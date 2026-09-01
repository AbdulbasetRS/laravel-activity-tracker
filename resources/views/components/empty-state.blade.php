@props(['title' => 'No activities found', 'description' => 'Try changing your search or filters.', 'resetUrl' => null])
<div class="at-empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 6h16M4 12h10M4 18h6"/></svg>
    <h3>{{ $title }}</h3>
    <p>{{ $description }}</p>
    @if ($resetUrl)
        <a href="{{ $resetUrl }}" class="at-btn">Reset filters</a>
    @endif
</div>
