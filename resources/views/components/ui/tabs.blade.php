@props(['items', 'label'])
@php
    foreach ($items as $item) {
        if (! isset($item['label'], $item['url'])) {
            throw new \InvalidArgumentException('Tab item needs label and url.');
        }
    }
@endphp
<nav {{ $attributes->class(['ds-tabs']) }} aria-label="{{ $label }}">
@foreach ($items as $item)
<a href="{{ $item['url'] }}" class="ds-tabs__tab"@if (! empty($item['active'])) aria-current="page"@endif>{{ $item['label'] }}@if (isset($item['count']))<span class="ds-tabs__count">{{ $item['count'] }}</span>@endif</a>
@endforeach
</nav>
