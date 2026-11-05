{{--
    Americano round for players (Turnier-Mixed / Turnier-Mixed-Mobil): group chips, match cards with the wave
    plan, Live-Wertung top 5. Pairings show only from "confirmed" on; a proposal shows the groups only.
    Americano has waves, no courts (gap 2). Data: RoundViewData::build().
--}}
@extends('mixed::layout')

@section('title', __('mixed::common.tab_round', ['number' => (int) $round->round_no]).' · '.$event->title)

@section('mixed-content')
@php
    use App\Modules\MixedTournament\Support\MixedFormat;

    $unknown = __('mixed::common.unknown_player');
    $names = static fn (array $ids): string => implode(', ', array_map(static fn (int $id): string => $labels[$id] ?? $unknown, $ids));
    $isProposed = $round->isProposed();
    $context = static function (int $groupNo, bool $extra) use ($waves, $waveCount): string {
        $parts = [__('mixed::common.group', ['number' => $groupNo + 1])];
        if ($extra) {
            $parts[] = __('mixed::common.extra_group');
        }
        if ($waveCount > 1 && isset($waves[$groupNo])) {
            $parts[] = __('mixed::common.wave', ['number' => $waves[$groupNo]]);
        }

        return implode(' · ', $parts);
    };
@endphp
@component('mixed::partials.event-head', ['event' => $event, 'roundCount' => $rounds->count()])
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

@if ($byGroup === [])
    <x-ui.notice tone="neutral">{{ __('mixed::common.no_proposal') }}</x-ui.notice>
@else
    @if ($playsTwice !== [])
        <x-ui.notice tone="attention" :title="__('mixed::common.plays_twice_title')">
            <p>{{ $names($playsTwice) }}</p>
            <p class="text-body-s">{{ __('mixed::common.plays_twice_hint') }}</p>
        </x-ui.notice>
    @endif

    <div class="flex flex-wrap items-start gap-5">
        <div class="flex min-w-0 flex-[999_1_640px] flex-col gap-3.5">
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.badge tone="neutral">{{ __('mixed::common.distribution_label', ['label' => MixedFormat::distribution((string) $round->distribution)]) }}</x-ui.badge>
                @foreach ($byGroup as $groupNo => $matches)
                    @php
                        $total = count($matches);
                        $done = count(array_filter($matches, static fn ($m): bool => $m->games_a !== null));
                    @endphp
                    @if ($isProposed)
                        <x-ui.badge tone="neutral">{{ __('mixed::common.group', ['number' => $groupNo + 1]) }} · {{ trans_choice('mixed::common.players_count', count($groupPlayers[$groupNo]), ['count' => count($groupPlayers[$groupNo])]) }}</x-ui.badge>
                    @elseif ($done === $total)
                        <x-ui.badge tone="good">{{ __('mixed::common.group', ['number' => $groupNo + 1]) }} · {{ __('mixed::common.group_done') }}</x-ui.badge>
                    @else
                        <x-ui.badge tone="attention">{{ __('mixed::common.group', ['number' => $groupNo + 1]) }} · {{ $done }}/{{ $total }}</x-ui.badge>
                    @endif
                @endforeach
            </div>

            @if ($waveCount > 1)
                <p class="text-body-s text-ink-2">{{ __('mixed::common.waves_hint', ['courts' => $courtCount, 'waves' => $waveCount]) }}</p>
            @endif

            @if ($isProposed)
                <x-ui.notice tone="neutral">{{ __('mixed::common.proposed_hint') }}</x-ui.notice>
                <div class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,17rem),1fr))] gap-3.5">
                    @foreach ($groupPlayers as $groupNo => $players)
                        <x-ui.card as="article">
                            <div class="flex flex-col gap-2.5">
                                <p class="text-label uppercase text-ink-3">{{ $context($groupNo, (bool) $byGroup[$groupNo][0]->is_extra) }}</p>
                                <ul class="flex flex-wrap gap-1.5">
                                    @foreach ($players as $pid)
                                        <li><x-ui.badge :tone="in_array($pid, $playsTwice, true) ? 'attention' : 'neutral'">{{ $labels[$pid] ?? $unknown }} ({{ MixedFormat::gender($genders[$pid] ?? null) }})@if (in_array($pid, $playsTwice, true)) · {{ __('mixed::common.twice') }}@endif</x-ui.badge></li>
                                    @endforeach
                                </ul>
                            </div>
                        </x-ui.card>
                    @endforeach
                </div>
            @else
                <div class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,17rem),1fr))] gap-3.5">
                    @foreach ($byGroup as $groupNo => $matches)
                        @foreach ($matches as $match)
                            @include('mixed::partials.match-card', ['match' => $match, 'labels' => $labels, 'context' => $context($groupNo, (bool) $match->is_extra)])
                        @endforeach
                    @endforeach
                </div>
            @endif
        </div>

        @include('mixed::partials.top5', ['top5' => $top5, 'labels' => $labels, 'event' => $event])
    </div>
@endif

@include('mixed::partials.scroll-restore', ['scrollId' => (int) $round->id])
@endsection
