@props(['icon', 'label' => null, 'variant' => 'secondary', 'type' => 'button', 'href' => null, 'disabled' => false])
@php
    if ($label === null || $label === '') {
        throw new \InvalidArgumentException('Icon button needs a label.');
    }
    if (! in_array($variant, ['secondary', 'chrome', 'text'], true)) {
        throw new \InvalidArgumentException("Unknown button variant [{$variant}].");
    }
    $classes = 'ds-icon-btn ds-icon-btn--'.$variant;
@endphp
@if ($href !== null)
<a href="{{ $href }}" {{ $attributes->class([$classes]) }} aria-label="{{ $label }}"{!! $disabled ? ' aria-disabled="true" tabindex="-1"' : '' !!}><x-ui.icon :name="$icon" /></a>
@else
<button type="{{ $type }}" {{ $attributes->class([$classes]) }} aria-label="{{ $label }}"{!! $disabled ? ' disabled' : '' !!}><x-ui.icon :name="$icon" /></button>
@endif
