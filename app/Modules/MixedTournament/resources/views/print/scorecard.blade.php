{{--
    Printable Americano scorecard (old scorecard.phtml): one slip per group, ordered by wave, page break after
    the last group of each wave. The wave number steers order and breaks only and is never printed; the result
    boxes are always empty (no score reaches this view). Cards from ScorecardBuilder::americano (group_no is
    zero-based).
--}}
@extends('mixed::print.layout')

@section('title', __('mixed::print.title_round', ['title' => $event->title, 'round' => $round->round_no]))

@section('content')
<div class="sc-head">
    <h1>{{ __('mixed::print.heading_round', ['title' => $event->title, 'round' => $round->round_no]) }}</h1>
    <div class="sc-sub">{{ __('mixed::print.sub_round', ['date' => $date, 'mode' => \App\Modules\MixedTournament\Support\MixedFormat::mode((string) $event->mode)]) }}</div>
    @if ($sitters !== [])
        <div class="sc-sitters"><strong>{{ __('mixed::print.sitters') }}</strong> {{ implode(', ', $sitters) }}</div>
    @endif
</div>

@if ($cards === [])
    <p>{{ __('mixed::print.no_matches') }}</p>
@else
    <div class="sc-grid">
        @foreach ($cards as $card)
            <section class="sc-card{{ $card['ends_wave'] ? ' sc-wave-end' : '' }}">
                <h2 class="sc-card-title">{{ __($card['is_extra'] ? 'mixed::print.group_extra' : 'mixed::print.group', ['n' => $card['group_no'] + 1]) }}</h2>
                <div class="sc-card-sub">{{ __('mixed::print.card_sub', ['title' => $event->title, 'round' => $round->round_no, 'date' => $date]) }}</div>

                <div class="sc-section">{{ __('mixed::print.players') }}</div>
                <ol class="sc-players">
                    @foreach ($card['players'] as $player)
                        <li>{{ __('mixed::print.player', ['no' => $player['no'], 'label' => $player['label'], 'gender' => $player['gender']]) }}</li>
                    @endforeach
                </ol>

                <div class="sc-section">{{ __('mixed::print.pairings') }}</div>
                <ol class="sc-pairs">
                    @foreach ($card['pairings'] as $pairing)
                        <li class="sc-pair">
                            <span class="sc-no">{{ $pairing['no'] }}</span>
                            <span class="sc-team">{{ $pairing['team_a'] }}</span>
                            <span class="sc-boxes" aria-hidden="true"><span class="sc-box"></span><span class="sc-colon">:</span><span class="sc-box"></span></span>
                            <span class="sc-team sc-team-r">{{ $pairing['team_b'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach
    </div>
@endif
@endsection
