{{--
    Printable Mexicano scorecard (old scorecard-mexicano.phtml): one slip per match with the court (CourtNames)
    and no separate player list (the group is exactly the four names of the pairing). Page break after the last
    match of each wave; the wave number is never printed, the result boxes are always empty.
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
                <h2 class="sc-card-title">{{ __('mixed::print.group_court', ['n' => $card['group_no'] + 1, 'court' => \App\Modules\TournamentBase\Support\CourtNames::label($card['court'])]) }}</h2>
                <div class="sc-card-sub">{{ __('mixed::print.card_sub_mexicano', ['round' => $round->round_no, 'date' => $date]) }}</div>

                <ol class="sc-pairs sc-pairs--single">
                    @foreach ([$card['team_a'], $card['team_b']] as $team)
                        <li class="sc-pair">
                            <span class="sc-team">{{ $team }}</span>
                            <span class="sc-boxes" aria-hidden="true"><span class="sc-box"></span></span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach
    </div>
@endif
@endsection
