@props(['variant' => 'primary', 'type' => 'button', 'href' => null, 'icon' => null, 'block' => true, 'disabled' => false])
@php
    if (! in_array($variant, ['primary', 'secondary', 'dark', 'danger', 'text'], true)) {
        throw new \InvalidArgumentException("Unknown button variant [{$variant}].");
    }
    $classes = 'ds-btn ds-btn--'.$variant.($block ? ' ds-btn--block' : '');
@endphp
@if ($href !== null)
<a href="{{ $href }}" {{ $attributes->class([$classes]) }}{!! $disabled ? ' aria-disabled="true" tabindex="-1"' : '' !!}>@if ($icon)<x-ui.icon :name="$icon" />@endif<span>{{ $slot }}</span></a>
@else
<button type="{{ $type }}" {{ $attributes->class([$classes]) }}{!! $disabled ? ' disabled' : '' !!}>@if ($icon)<x-ui.icon :name="$icon" />@endif<span>{{ $slot }}</span></button>
@endif
