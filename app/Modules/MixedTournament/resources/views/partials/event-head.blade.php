{{--
    Event header: back link to the event list, title, badges (Testmodus, Standard), meta line
    "date · n Teilnehmer · n Runden · mode", optional subtitle and actions.
    Use with @component('mixed::partials.event-head', ['event' => $event, 'subtitle' => ...]) and an
    optional @slot('actions').
    The back link goes to the member event list (mixed.events, read-only, works without admin mode and while a
    default event exists); on admin pages to the admin event list.
--}}
@php
    use App\Modules\MixedTournament\Support\MixedFormat;

    $participantCount = $participantCount ?? $event->participants()->count();
    $roundCount = $roundCount ?? $event->rounds()->count();
    $meta = implode(' · ', [
        MixedFormat::date($event->event_date),
        trans_choice('mixed::common.meta_participants', $participantCount, ['count' => $participantCount]),
        trans_choice('mixed::common.meta_rounds', $roundCount, ['count' => $roundCount]),
        MixedFormat::mode((string) $event->mode),
    ]);
    $hasActions = isset($actions) && trim((string) $actions) !== '';
    $backUrl = request()->routeIs('mixed.admin.*') ? route('mixed.admin.index') : route('mixed.events');
@endphp
<div class="flex flex-col gap-2">
    <a href="{{ $backUrl }}" class="inline-flex min-h-touch-min items-center gap-1 self-start text-body-s font-semibold text-ink-2 no-underline hover:text-clay-hover">
        <x-ui.icon name="chevron-left" :size="16" />{{ __('mixed::common.back_to_list') }}
    </a>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 flex-col gap-1">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="font-display text-display-l text-ink md:text-display-xl">{{ $event->title }}</h1>
                @if ($event->is_test)
                    <x-ui.badge tone="attention">{{ __('mixed::common.test_mode_badge') }}</x-ui.badge>
                @endif
                @if ($event->is_default)
                    <x-ui.badge tone="neutral">{{ __('mixed::common.default_badge') }}</x-ui.badge>
                @endif
            </div>
            <p class="text-body text-ink-2">{{ $meta }}</p>
            @if (! empty($subtitle))
                <p class="font-display text-display-s text-ink">{{ $subtitle }}</p>
            @endif
        </div>
        @if ($hasActions)
            <div class="flex flex-wrap gap-2.5">{{ $actions }}</div>
        @endif
    </div>
</div>
