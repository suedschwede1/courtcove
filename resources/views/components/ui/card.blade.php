@props(['title' => null, 'eyebrow' => null, 'level' => 2, 'tone' => 'default', 'padding' => 'md', 'as' => 'section', 'actions' => null, 'footer' => null])
@php
    $toneClass = [
        'default' => 'ds-card--default',
        'highlight' => 'ds-card--highlight ds-on-chrome',
    ][$tone];
    $padClass = [
        'md' => 'ds-card--pad-md',
        'lg' => 'ds-card--pad-lg',
        'none' => 'ds-card--pad-none',
    ][$padding];
    $tag = in_array($as, ['section', 'article', 'div'], true) ? $as : 'section';
    $heading = 'h'.min(6, max(1, (int) $level));
    $hasHeader = $title !== null || $eyebrow !== null || ($actions !== null && trim((string) $actions) !== '');
    $hasFooter = $footer !== null && trim((string) $footer) !== '';
@endphp
<{{ $tag }} {{ $attributes->class(['ds-card', $toneClass, $padClass]) }}>
@if ($hasHeader)
<header class="ds-card__header">
@if ($eyebrow !== null)
<p class="ds-card__eyebrow">{{ $eyebrow }}</p>
@endif
@if ($title !== null)
<{{ $heading }} class="ds-card__title">{{ $title }}</{{ $heading }}>
@endif
@if ($actions !== null && trim((string) $actions) !== '')
<div class="ds-card__actions">{{ $actions }}</div>
@endif
</header>
@endif
<div class="ds-card__body">{{ $slot }}</div>
@if ($hasFooter)
<footer class="ds-card__footer">{{ $footer }}</footer>
@endif
</{{ $tag }}>
