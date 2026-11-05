@props(['label', 'value', 'meta' => null])
<div {{ $attributes->class(['ds-kpi']) }}>
<p class="ds-kpi__label">{{ $label }}</p>
<p class="ds-kpi__value">{{ $value }}</p>
@if ($meta !== null)
<p class="ds-kpi__meta">{{ $meta }}</p>
@endif
</div>
