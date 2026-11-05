{{--
    My matches (old index/mine.phtml, mockup Turnier-Meine-Spiele-Mobil): own name with ITN, group and
    competition, key figures, then one card per match ("gegen X", status or Sieg/Niederlage, date or result,
    entry button by the match-card write rule, "Ergebnis ansehen" for played matches, WhatsApp to the
    opponent for participants). The next match is the highlighted card.
--}}
@extends('tournament::layout')

@section('tournament-content')
@php
    use App\Modules\Tournament\Support\MatchPresenter;
    use App\Modules\TournamentBase\Support\PlayerName;
    use App\Modules\TournamentBase\Support\WhatsApp;
    use Illuminate\Support\Facades\Route;

    $presenter = app(MatchPresenter::class);
@endphp

@if ($me === null)
    <x-ui.notice tone="neutral">{{ __('tournament::mine.not_participant') }}</x-ui.notice>
@else
@php
    $tpid = (int) $access->tpid;
    $wins = 0;
    $losses = 0;
    $openCount = 0;
    foreach ($matches as $match) {
        if (! $match['winner']) {
            $openCount++;
        } elseif ((int) $match['winner'] === $tpid) {
            $wins++;
        } else {
            $losses++;
        }
    }
    $meLine = array_filter([
        __('tournament::mine.itn', ['itn' => number_format((float) $me['itn_rated'], 1, ',', '')]),
        $me['grp'] ? __('tournament::mine.group', ['group' => $me['grp']]) : __('tournament::mine.not_drawn'),
        $me['grp'] ? $competition : null,
    ]);
@endphp

<div class="flex flex-col gap-1">
    <h2 class="font-display text-display-l text-ink">{{ $me['label'] }}</h2>
    <p class="text-ink-2">{{ implode(' · ', $meLine) }}</p>
</div>

<div class="grid grid-cols-3 gap-3">
    <x-ui.kpi :label="__('tournament::mine.kpi_wins')" :value="$wins.':'.$losses" />
    <x-ui.kpi :label="__('tournament::mine.kpi_rank')" :value="$row ? (int) $row['rank'].'.' : '–'" />
    <x-ui.kpi :label="__('tournament::mine.kpi_open')" :value="$openCount" />
</div>

@if ($row && ! empty($row['needs_lot']))
    <x-ui.notice tone="attention">{{ __('tournament::mine.lot') }}</x-ui.notice>
@endif

@if (! $matches)
    <p class="text-ink-2">{{ __('tournament::mine.no_matches') }}</p>
@endif

<div class="grid grid-cols-1 gap-3 md:grid-cols-2">
    @foreach ($matches as $match)
        @php
            $opponentTpid = (int) $match['tpid_a'] === $tpid ? $match['tpid_b'] : $match['tpid_a'];
            $opponent = $opponentTpid !== null ? ($players[(int) $opponentTpid] ?? null) : null;
            $won = $match['winner'] && (int) $match['winner'] === $tpid;
            $lost = $match['winner'] && ! $won;
            $state = $presenter->state($match);
            [$tone, $badgeKey] = match (true) {
                $won => ['good', 'won'],
                $lost => ['problem', 'lost'],
                $state === 'scheduled' => ['neutral', 'scheduled'],
                default => ['attention', 'open'],
            };
            $area = $match['grp'] !== null ? __('tournament::mine.group', ['group' => $match['grp']]) : (string) ($format->phase((string) $match['phase'])['label'] ?? $match['phase']);
            $groupText = $match['grp'] !== null ? (string) $match['grp'] : $area;
            $props = $presenter->present($match, $players, $access, $tournament);
            $isNext = $next !== null && (int) $next['tmid'] === (int) $match['tmid'];
            $phone = $showContacts && $opponent !== null ? (string) $opponent['phone'] : '';
            $waText = $match['played']
                ? __('tournament::mine.whatsapp_scheduled', ['name' => $opponent ? PlayerName::first((string) $opponent['label']) : '', 'group' => $groupText, 'date' => date('d.m.Y', (int) strtotime((string) $match['played']))])
                : __('tournament::mine.whatsapp_open', ['name' => $opponent ? PlayerName::first((string) $opponent['label']) : '', 'group' => $groupText]);
            $wa = $phone !== '' ? WhatsApp::url($phone, $waText) : '';
        @endphp
        <x-ui.card as="article" :tone="$isNext ? 'highlight' : 'default'" :eyebrow="$isNext ? __('tournament::mine.next') : null">
            <div class="flex flex-col gap-2.5">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="font-display text-display-s">{{ $opponent !== null ? __('tournament::mine.opponent', ['name' => $opponent['label']]) : __('tournament::mine.opponent_open') }}</h3>
                    <x-ui.badge :tone="$tone">{{ __('tournament::mine.badge.'.$badgeKey) }}</x-ui.badge>
                </div>
                <p class="text-body-s">
                    {{ $area }} ·
                    @if ($match['winner'])
                        <span class="tabular-nums">{{ __($won ? 'tournament::mine.won' : 'tournament::mine.lost', ['score' => (string) $match['score']]) }}@if ($match['retired'] !== 'none') {{ __('tournament::mine.walkover') }}@endif</span>
                        @unless ($match['confirmed_uid'])
                            · <span @class(['text-warn-ink' => ! $isNext])>{{ __('tournament::mine.not_confirmed') }}</span>
                        @endunless
                    @else
                        {{ $presenter->meta($match) ?? __('tournament::mine.no_date') }}
                    @endif
                </p>
                @php
                    $button = $props['action'];
                    if ($match['winner'] && Route::has('tournament.match')) {
                        $button = ['label' => __('tournament::mine.view_result'), 'url' => route('tournament.match', ['tmid' => (int) $match['tmid']]), 'variant' => 'secondary'];
                    }
                @endphp
                @if ($button !== null || $wa !== '')
                    <div class="flex flex-wrap gap-2">
                        @if ($button !== null)
                            {{-- as in ZF2 and the mockup: entry buttons primary, "Ergebnis ansehen" secondary --}}
                            <x-ui.button :href="$button['url']" :variant="$match['winner'] ? 'secondary' : 'primary'" :block="false">{{ $button['label'] }}</x-ui.button>
                        @endif
                        @if ($wa !== '')
                            <x-ui.button variant="secondary" :block="false" :href="$wa" target="_blank" rel="noopener">{{ __('tournament::mine.whatsapp') }}</x-ui.button>
                        @endif
                    </div>
                @endif
            </div>
        </x-ui.card>
    @endforeach
</div>
@endif
@endsection
