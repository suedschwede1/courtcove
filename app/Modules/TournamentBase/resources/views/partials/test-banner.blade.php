{{--
    Admin test mode and test tournament bars (old partial/nav.phtml). The test mode must be impossible to
    overlook: whoever forgets that he uses another player's view draws wrong conclusions.
    Needs: $simulation (AccessContext::$simulation or null), $stopUrl (POST target, sends mode=off).
    Optional: $testTournament (title of the selected test tournament; both bars can show at once).
--}}
@if (! empty($simulation))
<x-ui.notice tone="attention" :title="__('tournament-base::common.test_mode.title')">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p>{{ __('tournament-base::common.test_mode.view_of', ['label' => $simulation['label']]) }}<br><span class="text-ink-2">{{ __('tournament-base::common.test_mode.read_only') }}</span></p>
        <form method="post" action="{{ $stopUrl }}">
            @csrf
            <input type="hidden" name="mode" value="off">
            <x-ui.button type="submit" variant="secondary" :block="false">{{ __('tournament-base::common.test_mode.stop') }}</x-ui.button>
        </form>
    </div>
</x-ui.notice>
@endif
@if (! empty($testTournament))
<x-ui.notice tone="neutral" :title="__('tournament-base::common.test_tournament.title')">{{ __('tournament-base::common.test_tournament.text', ['title' => $testTournament]) }}</x-ui.notice>
@endif
