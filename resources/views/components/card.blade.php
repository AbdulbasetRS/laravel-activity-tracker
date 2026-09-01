@props(['header' => null])
<div {{ $attributes->class(['at-card']) }}>
    @if ($header)
        <div class="at-card-header">{{ $header }}</div>
    @endif
    <div class="at-card-body">
        {{ $slot }}
    </div>
</div>
