{{--
    Mexicano round for players (Turnier-Mexicano-Mobil): round chips instead of one tab per round, sitters,
    match cards with court and wave, Live-Wertung top 5. Data: RoundViewData::build().
--}}
@extends('mixed::layout')

@section('title', __('mixed::common.tab_round', ['number' => (int) $round->round_no]).' · '.$event->title)

@section('mixed-content')
@php
    use App\Modules\TournamentBase\Support\CourtNames;

    $unknown = __('mixed::common.unknown_player');
    $check = __('mixed::common.round_done');
@endphp
@component('mixed::partials.event-head', ['event' => $event, 'roundCount' => $rounds->count(), 'subtitle' => __('mixed::common.tab_round', ['number' => (int) $round->round_no])])
    @slot('actions')
        @if ($byGroup !== [] && $round->isConfirmed())
            <x-ui.button variant="secondary" :block="false" :href="route('mixed.scorecard', ['round' => (int) $round->id])" target="_blank" rel="noopener">{{ __('mixed::common.print_scorecard') }}</x-ui.button>
        @endif
        @if ($canScore)
            <x-ui.button :block="false" :href="route('mixed.admin.round', ['round' => (int) $round->id])">{{ $canAdmin ? __('mixed::common.admin_round_link') : __('mixed::common.enter_results') }}</x-ui.button>
        @endif
    @endslot
@endcomponent

@include('mixed::partials.tabs', ['event' => $event, 'active' => 'round:'.$round->id, 'canAdmin' => $canAdmin])

<nav aria-label="{{ __('mixed::common.rounds_title') }}" class="relative flex gap-2 overflow-x-auto pb-1" x-data x-init="const a = $el.querySelector('[aria-current]'); if (a) { $el.scrollLeft = a.offsetLeft - 16 }">
    @foreach ($rounds as $item)
        @if ($item->isCompleted())
            <x-ui.chip :href="route('mixed.round', ['round' => (int) $item->id])" :active="(int) $item->id === (int) $round->id" class="shrink-0 gap-1.5">{{ __('mixed::common.tab_round', ['number' => (int) $item->round_no]) }}<x-ui.icon name="check" :size="16" /><span class="sr-only">{{ $check }}</span></x-ui.chip>
        @else
            <x-ui.chip :href="route('mixed.round', ['round' => (int) $item->id])" :active="(int) $item->id === (int) $round->id" class="shrink-0">{{ __('mixed::common.tab_round', ['number' => (int) $item->round_no]) }} {{ __('mixed::common.round_open_suffix') }}</x-ui.chip>
        @endif
    @endforeach
</nav>

@if ($byGroup === [])
    <x-ui.notice tone="neutral">{{ __('mixed::common.no_proposal') }}</x-ui.notice>
@else
    @if ($sitters !== [])
        <x-ui.notice tone="attention">
            <p><strong>{{ __('mixed::common.sitters_title') }}</strong> {{ implode(', ', array_map(static fn (int $id): string => $labels[$id] ?? $unknown, $sitters)) }}</p>
            <p class="text-body-s">{{ __('mixed::common.sitters_hint') }}</p>
        </x-ui.notice>
    @endif

    <div class="flex flex-wrap items-start gap-5">
        <div class="flex min-w-0 flex-[999_1_640px] flex-col gap-3.5">
            @if ($waveCount > 1)
                <p class="text-body-s text-ink-2">{{ __('mixed::common.waves_hint_mexicano', ['courts' => $courtCount, 'groups' => count($byGroup), 'waves' => $waveCount]) }}</p>
            @endif
            <div class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,17rem),1fr))] gap-3.5">
                @foreach ($byGroup as $groupNo => $matches)
                    @php
                        $parts = [__('mixed::common.group', ['number' => $groupNo + 1]), CourtNames::label($slots[$groupNo]['court'])];
                        if ($waveCount > 1) {
                            $parts[] = __('mixed::common.wave', ['number' => $slots[$groupNo]['wave']]);
                        }
                    @endphp
                    @foreach ($matches as $match)
                        @include('mixed::partials.match-card', ['match' => $match, 'labels' => $labels, 'context' => implode(' · ', $parts)])
                    @endforeach
                @endforeach
            </div>
        </div>

        @include('mixed::partials.top5', ['top5' => $top5, 'labels' => $labels, 'event' => $event])
    </div>
@endif

@include('mixed::partials.scroll-restore', ['scrollId' => (int) $round->id])
@endsection
