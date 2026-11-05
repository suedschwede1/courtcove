@props(['id', 'show', 'close' => null, 'eyebrow' => null, 'label' => null, 'size' => 'md'])
@php
    if (! in_array($size, ['md', 'lg', 'frame'], true)) {
        throw new \InvalidArgumentException("Unknown modal size [{$size}].");
    }
    $closeStatement = $close ?? "{$show} = false";
    $hasTitle = trim((string) ($title ?? '')) !== '';
    $hasFooter = trim((string) ($footer ?? '')) !== '';
    // A frame modal hosts a page that brings its own title bar and close button, so the shell adds no header of its own.
    $isFrame = $size === 'frame';
@endphp
<div id="{{ $id }}" {{ $attributes->class(['ds-modal']) }} role="dialog" aria-modal="true" @if ($hasTitle) aria-labelledby="{{ $id }}-title" @elseif ($label !== null) aria-label="{{ $label }}" @endif x-data="dsModal" x-show="{{ $show }}" x-cloak x-transition.opacity x-effect="sync({{ $show }})" x-bind:data-open="({{ $show }}) ? '' : null" @keydown.escape.stop="{{ $closeStatement }}" @keydown.tab="trap($event)">
    <div class="ds-modal__backdrop" @click="{{ $closeStatement }}"></div>
    <div class="ds-modal__panel ds-modal__panel--{{ $size }}">
        @unless ($isFrame)
        <span class="ds-modal__handle" aria-hidden="true"></span>
        <header class="ds-modal__header">
            <div class="ds-modal__heading">
                @if ($eyebrow !== null)
                    <p class="ds-modal__eyebrow">{{ $eyebrow }}</p>
                @endif
                @if ($hasTitle)
                    <h2 id="{{ $id }}-title" class="ds-modal__title">{{ $title }}</h2>
                @endif
            </div>
            <x-ui.icon-button icon="close" variant="text" class="ds-modal__close" :label="__('booking.modal.close')" x-on:click="{{ $closeStatement }}" />
        </header>
        @endunless
        <div class="ds-modal__body">{{ $slot }}</div>
        @if ($hasFooter)
            <div class="ds-modal__footer">{{ $footer }}</div>
        @endif
    </div>
</div>
