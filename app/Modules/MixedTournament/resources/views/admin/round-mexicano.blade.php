{{--
    Mexicano round workspace (old admin/round-mexicano.phtml): round chips, one match per group with court and
    wave, the sitters. proposed: sitter swap (select sitter, select player, "Aussetzer tauschen"; no player swap,
    the pairing follows from the standings), Runde löschen / Bestätigen. confirmed: the five result buttons
    (score users and admins; one-shot scroll restore), Runde abschließen (admins). completed: Runde wieder
    öffnen (admins). Data: RoundViewData::build() plus $participants and $roundParticipantIds.
--}}
@extends('mixed::admin.layout')

@section('title', __('mixed::common.tab_round', ['number' => (int) $round->round_no]).' · '.$event->title)

@section('mixed-admin-content')
@php
    use App\Modules\TournamentBase\Support\CourtNames;

    $unknown = __('mixed::common.unknown_player');
    $check = __('mixed::common.round_done');
    $isProposed = $round->isProposed();
    $isConfirmed = $round->isConfirmed();
    $withResults = $isConfirmed && $canScore;
    $playing = [];
    foreach ($groupPlayers as $players) {
        foreach ($players as $pid) {
            $playing[$pid] = $labels[$pid] ?? $unknown;
        }
    }
    $sitterOptions = [];
    foreach ($sitters as $sid) {
        $sitterOptions[$sid] = $labels[$sid] ?? $unknown;
    }
@endphp
@component('mixed::admin.partials.round-head', ['event' => $event, 'round' => $round, 'canAdmin' => $canAdmin])
    @slot('actions')
        @if ($byGroup !== [] && $isConfirmed)
            <x-ui.button variant="secondary" :block="false" :href="route('mixed.scorecard', ['round' => (int) $round->id])" target="_blank" rel="noopener">{{ __('mixed::common.print_scorecard') }}</x-ui.button>
        @endif
    @endslot
@endcomponent


@if ($withResults)
    @include('mixed::partials.scroll-restore', ['scrollId' => $round->id])
@endif

<nav aria-label="{{ __('mixed::common.rounds_title') }}" class="relative flex gap-2 overflow-x-auto pb-1" x-data x-init="const a = $el.querySelector('[aria-current]'); if (a) { $el.scrollLeft = a.offsetLeft - 16 }">
    @foreach ($rounds as $item)
        @if ($item->isCompleted())
            <x-ui.chip :href="route('mixed.admin.round', ['round' => (int) $item->id])" :active="(int) $item->id === (int) $round->id" class="shrink-0 gap-1.5">{{ __('mixed::common.tab_round', ['number' => (int) $item->round_no]) }}<x-ui.icon name="check" :size="16" /><span class="sr-only">{{ $check }}</span></x-ui.chip>
        @else
            <x-ui.chip :href="route('mixed.admin.round', ['round' => (int) $item->id])" :active="(int) $item->id === (int) $round->id" class="shrink-0">{{ __('mixed::common.tab_round', ['number' => (int) $item->round_no]) }} {{ __('mixed::common.round_open_suffix') }}</x-ui.chip>
        @endif
    @endforeach
</nav>

@if ($byGroup === [])
    <x-ui.notice tone="neutral">{{ __('mixed::common.no_proposal') }}</x-ui.notice>
@else
    @if ($sitters !== [])
        <x-ui.notice tone="attention">
            <p><strong>{{ __('mixed::common.sitters_title') }}</strong> {{ implode(', ', $sitterOptions) }}</p>
            <p class="text-body-s">{{ $canAdmin && $isProposed ? __('mixed::admin.round.sitters_hint') : __('mixed::common.sitters_hint') }}</p>
        </x-ui.notice>
    @endif

    <div class="flex flex-wrap items-start gap-5">
        <div class="flex min-w-0 flex-[999_1_640px] flex-col gap-3.5">
            @if ($canAdmin && $isProposed && $sitters !== [] && $playing !== [])
                <x-ui.card :title="__('mixed::admin.round.sitter_swap_title')">
                    <form method="POST" action="{{ route('mixed.admin.swap-sitter', ['round' => (int) $round->id]) }}" class="grid items-end gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                        @csrf
                        <x-ui.field for="mixed-sitter" :label="__('mixed::admin.round.sitter')">
                            <x-ui.select name="sitter_id" id="mixed-sitter" :options="$sitterOptions" />
                        </x-ui.field>
                        <x-ui.field for="mixed-sitter-player" :label="__('mixed::admin.round.sitter_player')">
                            <x-ui.select name="player_id" id="mixed-sitter-player" :options="$playing" />
                        </x-ui.field>
                        <x-ui.button type="submit" variant="dark" :block="false">{{ __('mixed::admin.round.sitter_swap') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif

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
                        @include('mixed::partials.match-card', ['match' => $match, 'labels' => $labels, 'context' => implode(' · ', $parts), 'withResults' => $withResults])
                    @endforeach
                @endforeach
            </div>

            <x-ui.card>
                <div class="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-center md:justify-between">
                    @if ($canAdmin && $isProposed)
                        <form method="POST" action="{{ route('mixed.admin.delete-round', ['round' => (int) $round->id]) }}" x-data x-on:submit="if (! confirm(@js(__('mixed::admin.round.delete_confirm')))) $event.preventDefault()">
                            @csrf
                            <x-ui.button type="submit" variant="danger">{{ __('mixed::admin.round.delete') }}</x-ui.button>
                        </form>
                        <form method="POST" action="{{ route('mixed.admin.confirm', ['round' => (int) $round->id]) }}">
                            @csrf
                            <x-ui.button type="submit">{{ __('mixed::admin.round.confirm') }}</x-ui.button>
                        </form>
                    @elseif ($canAdmin && $isConfirmed)
                        <p class="text-body-s text-ink-2 md:w-full">{{ __('mixed::admin.round.mexicano_confirmed_hint') }}</p>
                        <form method="POST" action="{{ route('mixed.admin.complete', ['round' => (int) $round->id]) }}" x-data x-on:submit="if (! confirm(@js(__('mixed::admin.round.complete_confirm_mexicano')))) $event.preventDefault()">
                            @csrf
                            <x-ui.button type="submit" variant="dark">{{ __('mixed::admin.round.complete') }}</x-ui.button>
                        </form>
                    @elseif ($canAdmin && $round->isCompleted())
                        <form method="POST" action="{{ route('mixed.admin.reopen', ['round' => (int) $round->id]) }}" x-data x-on:submit="if (! confirm(@js(__('mixed::admin.round.reopen_confirm_mexicano')))) $event.preventDefault()">
                            @csrf
                            <x-ui.button type="submit" variant="secondary">{{ __('mixed::admin.round.reopen') }}</x-ui.button>
                        </form>
                    @endif
                    <x-ui.button variant="text" :href="route('mixed.round', ['round' => (int) $round->id])">{{ __('mixed::admin.round.overview') }}</x-ui.button>
                </div>
            </x-ui.card>
        </div>

        @include('mixed::partials.top5', ['top5' => $top5, 'labels' => $labels, 'event' => $event, 'standingsUrl' => $canAdmin ? route('mixed.admin.standings', ['event' => (int) $event->id]) : route('mixed.standings', ['event' => (int) $event->id])])
    </div>
@endif
@endsection
