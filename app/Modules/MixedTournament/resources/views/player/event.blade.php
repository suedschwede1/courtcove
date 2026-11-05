{{--
    Event overview for players: participants and rounds. No own mockup: Turnier-Mixed header and tabs,
    Turnier-Teilnehmer-Mobil list pattern. Participants show label, D/H and the paused badge only.
--}}
@extends('mixed::layout')

@section('title', $event->title)

@section('mixed-content')
@php
    use App\Modules\MixedTournament\Support\MixedFormat;

    $activeCount = $participants->where('status', 'active')->count();
    $roundTone = ['proposed' => 'attention', 'confirmed' => 'good', 'completed' => 'good'];
@endphp
@component('mixed::partials.event-head', ['event' => $event, 'participantCount' => $participants->count(), 'roundCount' => $rounds->count()])
    @slot('actions')
        <x-ui.button variant="secondary" :block="false" :href="route('mixed.info')">{{ __('mixed::common.how_it_works') }}</x-ui.button>
        @if ($canAdmin)
            <x-ui.button :block="false" :href="route('mixed.admin.event', ['event' => (int) $event->id])">{{ __('mixed::common.admin_event_link') }}</x-ui.button>
        @endif
    @endslot
@endcomponent

@include('mixed::partials.tabs', ['event' => $event, 'active' => 'teilnehmer', 'canAdmin' => $canAdmin])

<div class="flex flex-wrap items-start gap-5">
    <section aria-labelledby="mixed-participants" class="flex min-w-0 flex-[999_1_640px] flex-col gap-3">
        <h2 id="mixed-participants" class="font-display text-display-m text-ink">
            {{ __('mixed::common.participants_title') }}
            <span class="font-body text-body font-normal text-ink-2">{{ __('mixed::common.participants_count', ['active' => $activeCount, 'total' => $participants->count()]) }}</span>
        </h2>
        @if ($participants->isEmpty())
            <x-ui.notice tone="neutral">{{ __('mixed::common.no_participants') }}</x-ui.notice>
        @else
            <ol class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,17rem),1fr))] gap-2.5">
                @foreach ($participants as $index => $participant)
                    <li>
                        <x-ui.card as="div" class="h-full">
                            <div class="flex items-center gap-3">
                                <span class="w-7 shrink-0 font-display text-display-s tabular-nums text-ink-3">{{ $index + 1 }}</span>
                                <span class="flex min-w-0 flex-1 flex-col">
                                    <span class="truncate font-bold text-ink">{{ $participant->label }}</span>
                                    {{-- Privacy: players see the gender letter only (as on the ZF2 round chips), never ITN or age. --}}
                                    <span class="text-body-s text-ink-2">{{ MixedFormat::gender($participant->gender) }}</span>
                                </span>
                                @if ($participant->status === 'paused')
                                    <x-ui.badge tone="neutral">{{ __('mixed::common.paused') }}</x-ui.badge>
                                @endif
                            </div>
                        </x-ui.card>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    <x-ui.card :title="__('mixed::common.rounds_title')" class="flex-[1_1_300px]">
        @if ($rounds->isEmpty())
            <p class="text-body text-ink-2">{{ __('mixed::common.no_rounds') }}</p>
        @else
            <ul class="flex flex-col">
                @foreach ($rounds as $round)
                    <li class="flex items-center justify-between gap-2 border-t border-line-soft py-2">
                        <a href="{{ route('mixed.round', ['round' => (int) $round->id]) }}" class="inline-flex min-h-touch-min items-center font-semibold text-clay underline hover:text-clay-hover">{{ __('mixed::common.tab_round', ['number' => (int) $round->round_no]) }}</a>
                        @if (isset($roundTone[$round->status]))
                            <x-ui.badge :tone="$roundTone[$round->status]">{{ __('mixed::common.round_status.'.$round->status) }}</x-ui.badge>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>
@endsection
