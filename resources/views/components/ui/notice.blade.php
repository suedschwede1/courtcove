@props(['tone' => 'attention', 'title' => null])
@php
    $toneClass = [
        'attention' => 'ds-notice--attention',
        'problem' => 'ds-notice--problem',
        'good' => 'ds-notice--good',
        'neutral' => 'ds-notice--neutral',
    ][$tone];
    $role = $tone === 'problem' ? 'alert' : 'status';
@endphp
<div {{ $attributes->merge(['class' => 'ds-notice '.$toneClass, 'role' => $role]) }}>@if ($title !== null)<p class="ds-notice__title">{{ $title }}</p>@endif{{ $slot }}</div>
