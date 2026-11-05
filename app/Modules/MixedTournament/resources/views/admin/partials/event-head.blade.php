{{--
    Header of the event admin tabs (Admin-Mixed-Event): back link, eyebrow, title with mode/default/test
    badges, facts "date · n aktiv / n gesamt · n Runden", then the event switcher (events the viewer
    administers), "Als Standard-Event markieren" and "Neues Event" (both global admins only).
    Needs: $event, $activeCount, $totalCount, $roundCount, $switchEvents, $isGlobalAdmin.
--}}
@php
    use App\Modules\MixedTournament\Support\MixedFormat;

    $facts = __('mixed::admin.head.facts', [
        'date' => MixedFormat::date($event->event_date),
        'active' => $activeCount,
        'total' => $totalCount,
        'rounds' => trans_choice('mixed::admin.head.rounds', $roundCount, ['count' => $roundCount]),
    ]);
    $switchOptions = [];
    foreach ($switchEvents as $switchEvent) {
        $switchOptions[route('mixed.admin.event', ['event' => (int) $switchEvent->id])] = $switchEvent->title.' · '.date('j.n.', (int) strtotime((string) $switchEvent->event_date));
    }
@endphp
<div class="flex flex-col gap-2">
    <a href="{{ route('mixed.admin.index') }}" class="inline-flex min-h-touch-min items-center gap-1 self-start text-body-s font-semibold text-ink-2 no-underline hover:text-clay-hover">
        <x-ui.icon name="chevron-left" :size="16" />{{ __('mixed::common.back_to_list') }}
    </a>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex min-w-0 flex-col gap-1">
            <p class="text-label uppercase text-ink-3">{{ __('mixed::admin.eyebrow') }}</p>
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="font-display text-display-l text-ink md:text-display-xl">{{ $event->title }}</h1>
                <x-ui.badge tone="neutral">{{ MixedFormat::mode((string) $event->mode) }}</x-ui.badge>
                @if ($event->is_default)
                    <x-ui.badge tone="good">{{ __('mixed::common.default_badge') }}</x-ui.badge>
                @endif
                @if ($event->is_test)
                    <x-ui.badge tone="attention">{{ __('mixed::common.test_mode_badge') }}</x-ui.badge>
                @endif
            </div>
            <p class="text-body text-ink-2">{{ $facts }}</p>
        </div>
        <div class="flex w-full flex-wrap items-center gap-2.5 md:w-auto">
            @if (count($switchOptions) > 1)
                <div class="min-w-0 flex-1 md:w-64 md:flex-none">
                    <x-ui.select name="event-switch" id="mixed-event-switch" :options="$switchOptions" :selected="route('mixed.admin.event', ['event' => (int) $event->id])" aria-label="{{ __('mixed::admin.head.switch_label') }}" x-data x-on:change="window.location.href = $event.target.value" />
                </div>
            @endif
            @if ($isGlobalAdmin && ! $event->is_default)
                <form method="POST" action="{{ route('mixed.admin.set-default', ['event' => (int) $event->id]) }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" :block="false">{{ __('mixed::admin.head.set_default') }}</x-ui.button>
                </form>
            @endif
            @if ($isGlobalAdmin)
                <x-ui.button :block="false" :href="route('mixed.admin.index').'#mixed-admin-new'">{{ __('mixed::admin.head.new_event') }}</x-ui.button>
            @endif
        </div>
    </div>
</div>
