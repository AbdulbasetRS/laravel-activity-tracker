@props(['label', 'value' => null, 'muted' => false])
<div class="at-meta-item">
    <div class="at-meta-label">{{ $label }}</div>
    <div class="at-meta-value {{ $muted ? 'at-muted' : '' }}">
        {{ $value !== null && $value !== '' ? $value : '—' }}{{ $slot->isNotEmpty() ? $slot : '' }}
    </div>
</div>
