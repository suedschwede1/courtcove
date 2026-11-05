{{--
    Header of the round workspace (Admin-Mixed-Runde): back link, event title as eyebrow, "Runde 3 – Vorschlag"
    and optional actions on the right.
    Use with @component('mixed::admin.partials.round-head', ['event' => $event, 'round' => $round, 'canAdmin' => $canAdmin])
    and an optional @slot('actions'). Event admins go back to the event, scorers to the list.
--}}
@php
    $hasActions = isset($actions) && trim((string) $actions) !== '';
    $back = $canAdmin ? route('mixed.admin.event', ['event' => (int) $event->id]) : route('mixed.admin.index');
@endphp
<div class="flex flex-col gap-2">
    <a href="{{ $back }}" class="inline-flex min-h-touch-min items-center gap-1 self-start text-body-s font-semibold text-ink-2 no-underline hover:text-clay-hover">
        <x-ui.icon name="chevron-left" :size="16" />{{ $canAdmin ? $event->title : __('mixed::common.back_to_list') }}
    </a>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex min-w-0 flex-col gap-1">
            <p class="text-label uppercase text-ink-3">{{ $event->title }}</p>
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="font-display text-display-l text-ink md:text-display-xl">{{ __('mixed::admin.round.title', ['number' => (int) $round->round_no, 'status' => __('mixed::common.round_status.'.$round->status)]) }}</h1>
                @if ($event->is_test)
                    <x-ui.badge tone="attention">{{ __('mixed::common.test_mode_badge') }}</x-ui.badge>
                @endif
            </div>
        </div>
        @if ($hasActions)
            <div class="flex flex-wrap gap-2.5">{{ $actions }}</div>
        @endif
    </div>
</div>
