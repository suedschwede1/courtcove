{{--
    Blank scorecards (old scorecard-blank.phtml): slips without names for printing in stock. Only two lines to
    fill in (group and round); no date, court or wave. Pairings are position codes from ScorecardTemplateService
    ("1+3" = position 1 with position 3), derived from the repo rotation, so 5er slips printed from the old live
    system show other codes.
--}}
@extends('mixed::print.layout')

@section('title', __('mixed::print.title_blank', ['title' => $event->title]))

@php
    $withSitting = collect($rows)->contains(fn (array $row): bool => $row['sitting'] !== '');
@endphp

@section('content')
<div class="sc-head">
    <h1>{{ __('mixed::print.heading_blank', ['title' => $event->title]) }}</h1>
    <div class="sc-sub">{{ __('mixed::print.sub_blank', ['mode' => \App\Modules\MixedTournament\Support\MixedFormat::mode((string) $event->mode), 'size' => $playerCount, 'count' => $count]) }}</div>
    <div class="sc-sitters">{{ __('mixed::print.blank_hint') }} <span class="sc-code">1+3</span> {{ __('mixed::print.blank_hint_example') }}</div>
</div>

<div class="sc-grid">
    @for ($i = 0; $i < $count; $i++)
        <section class="sc-card">
            <h2 class="sc-card-title">{{ __('mixed::print.blank_group') }} <span class="sc-line"></span> · {{ __('mixed::print.blank_round') }} <span class="sc-line"></span></h2>
            <div class="sc-card-sub">{{ $event->title }}</div>

            <div class="sc-section">{{ __('mixed::print.players') }}</div>
            @for ($p = 1; $p <= $playerCount; $p++)
                <div class="sc-name"><span>{{ $p }}.</span><span class="sc-name-line"></span></div>
            @endfor

            <div class="sc-section">{{ __('mixed::print.pairings') }}</div>
            <ol class="sc-pairs{{ $withSitting ? ' sc-pairs--sitting' : '' }}">
                @foreach ($rows as $n => $row)
                    <li class="sc-pair">
                        <span class="sc-no">{{ $n + 1 }}</span>
                        <span class="sc-team sc-code">{{ $row['team_a'] }}</span>
                        <span class="sc-boxes" aria-hidden="true"><span class="sc-box"></span><span class="sc-colon">:</span><span class="sc-box"></span></span>
                        <span class="sc-team sc-team-r sc-code">{{ $row['team_b'] }}</span>
                        @if ($row['sitting'] !== '')
                            <span class="sc-no sc-team-r">{{ __('mixed::print.sitting', ['n' => $row['sitting']]) }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>
    @endfor
</div>
@endsection
