@props(['data', 'label' => null])
@php($json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
<div>
    @if ($label)
        <div class="at-label">{{ $label }}</div>
    @endif
    <div class="at-json-viewer" data-at-json="{{ $json }}">
        <button type="button" class="at-btn at-btn-icon at-json-copy" data-at-json-copy aria-label="Copy JSON">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="11" height="11" rx="1"/><path d="M5 15V5a2 2 0 012-2h10"/></svg>
        </button>
        <pre>{{ $json }}</pre>
    </div>
</div>
