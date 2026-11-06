{{--
    Frame of every championship page: tournament title, the test mode / test tournament bars, the open token-draw
    hint, the module flash list and the page content (section tournament-content). The module navigation is the
    tournament sidebar (ChampionshipNavigation).
    Needs the shared page context (TournamentPage::build()): $tournament, $access, $stopUrl, $testTournament,
    $tokenHint.
--}}
@extends('tournament-base::layouts.module')

@section('title', $tournament->title)

@section('module-banner')
    @include('tournament-base::partials.test-banner', ['simulation' => $access->simulation, 'stopUrl' => $stopUrl, 'testTournament' => $testTournament])
    @if ($tokenHint !== null)
        <x-ui.notice tone="neutral" :title="__('tournament::common.token_hint.title')">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-ink-2">
                    @if ($tokenHint['deadline'])
                        {{ __('tournament::common.token_hint.until', ['deadline' => date('d.m.Y H:i', (int) strtotime($tokenHint['deadline']))]) }}
                    @else
                        {{ __('tournament::common.token_hint.voluntary') }}
                    @endif
                </p>
                @if ($tokenHint['url'] !== null)
                    <x-ui.button :href="$tokenHint['url']" variant="secondary" :block="false">{{ __('tournament::common.token_hint.pick') }}</x-ui.button>
                @endif
            </div>
        </x-ui.notice>
    @endif
@endsection

@section('module-content')
    @yield('tournament-content')
@endsection
