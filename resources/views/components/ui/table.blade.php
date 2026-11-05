@props(['caption' => null, 'variant' => 'list', 'stack' => true, 'head' => null])
@php
    if (! in_array($variant, ['list', 'kv'], true)) {
        throw new \InvalidArgumentException("Unknown table variant [{$variant}].");
    }
    $isKv = $variant === 'kv';
    $variantClass = $isKv ? 'ds-table--kv' : 'ds-table--list';
    $hasHead = ! $isKv && $head !== null && trim((string) $head) !== '';
@endphp
<div class="ds-table-wrap">
<table {{ $attributes->class(['ds-table', $variantClass, 'ds-table--stack' => ! $isKv && $stack]) }}>
@if ($caption !== null && $caption !== '')
<caption class="sr-only">{{ $caption }}</caption>
@endif
@if ($hasHead)
<thead>{{ $head }}</thead>
@endif
<tbody>{{ $slot }}</tbody>
</table>
</div>
