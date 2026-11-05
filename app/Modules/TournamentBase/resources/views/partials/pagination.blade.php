@props(['paginator', 'summary'])
<div class="flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-ink-3">{{ $summary }}</p>
    <x-ui.pagination :paginator="$paginator" />
</div>
