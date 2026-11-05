@props(['title', 'eyebrow' => null, 'intro' => null])
<header {{ $attributes->class(['flex flex-col gap-1']) }}>
@if ($eyebrow !== null && $eyebrow !== '')
<p class="text-label uppercase text-ink-3">{{ $eyebrow }}</p>
@endif
<h1 class="font-display text-display-l text-ink">{{ $title }}</h1>
@if ($intro !== null && $intro !== '')
<p class="text-body text-ink-2">{{ $intro }}</p>
@endif
</header>
