{{--
    Overview (old index/index.phtml, repo version): the viewer's next match, key figures, then either the
    group phase (progress per group with the table, promotion) or the KO phase (progress per competition,
    placements), switchable by chips.
--}}
@extends('tournament::layout')

@section('tournament-content')
@php
    use App\Modules\Tournament\Support\MatchPresenter;
    use App\Modules\TournamentBase\Support\PlayerName;
    use App\Modules\TournamentBase\Support\WhatsApp;
    use Illuminate\Support\Facades\Route;

    $presenter = app(MatchPresenter::class);
    $name = fn (int|string|null $tpid): string => $tpid !== null && isset($players[(int) $tpid]) ? (string) $players[(int) $tpid]['label'] : __('tournament::overview.unknown');
    $signed = fn (int|string $n): string => sprintf('%+d', (int) $n);
    $kpis = $view === 'ko' ? $bracket['totals'] : $counts;
@endphp

<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    @if ($next !== null)
        @php
            $props = $presenter->present($next, $players, $access, $tournament);
            $opponentTpid = (int) $next['tpid_a'] === (int) $access->tpid ? $next['tpid_b'] : $next['tpid_a'];
            $opponent = $opponentTpid !== null ? ($players[(int) $opponentTpid] ?? null) : null;
            $area = $next['grp'] !== null ? (string) $next['grp'] : (string) ($format->phase((string) $next['phase'])['label'] ?? $next['phase']);
        @endphp
        <x-ui.card tone="highlight" as="div" :eyebrow="__('tournament::overview.next_match')">
            <div class="flex flex-col gap-3">
                <p class="flex flex-wrap items-baseline gap-x-2">
                    <span class="font-display text-display-m">{{ $opponent !== null ? __('tournament::overview.opponent', ['name' => $opponent['label']]) : __('tournament::overview.opponent_open') }}</span>
                    <span class="text-body-s">{{ $next['grp'] !== null ? __('tournament::overview.group', ['group' => $area]) : $area }}</span>
                </p>
                <p>{{ $presenter->meta($next) ?? __('tournament::overview.no_date') }}</p>
                @if ($props['action'] !== null || ($showContacts && $opponent !== null && WhatsApp::url($opponent['phone'] ?? null) !== ''))
                    <div class="flex flex-wrap gap-2">
                        @if ($props['action'] !== null)
                            <x-ui.button :href="$props['action']['url']" :block="false">{{ $props['action']['label'] }}</x-ui.button>
                        @endif
                        @if ($showContacts && $opponent !== null && WhatsApp::url($opponent['phone'] ?? null) !== '')
                            <x-ui.button variant="secondary" :block="false" target="_blank" rel="noopener" :href="WhatsApp::url($opponent['phone'], __('tournament::overview.whatsapp_text', ['name' => PlayerName::first((string) $opponent['label']), 'group' => $area]))">{{ __('tournament::overview.whatsapp') }}</x-ui.button>
                        @endif
                    </div>
                @endif
            </div>
        </x-ui.card>
    @elseif (! $access->tpid)
        <x-ui.notice tone="neutral">{{ __('tournament::overview.not_participant') }}</x-ui.notice>
    @else
        <div></div>
    @endif

    <div class="grid grid-cols-3 gap-3">
        <x-ui.kpi :label="__('tournament::overview.kpi_played')" :value="(int) $kpis['played'].'/'.(int) $kpis['total']" />
        <x-ui.kpi :label="__('tournament::overview.kpi_scheduled')" :value="(int) $kpis['scheduled']" />
        <x-ui.kpi :label="__('tournament::overview.kpi_open')" :value="(int) $kpis['open']" />
    </div>
</div>

<div class="flex flex-wrap items-center justify-between gap-3">
    <h2 class="font-display text-display-m text-ink">{{ $view === 'ko' ? __('tournament::overview.ko_title') : __('tournament::overview.groups_title') }}</h2>
    @if ($koAvailable)
        <div role="group" aria-label="{{ __('tournament::overview.view_label') }}" class="flex flex-wrap gap-2">
            <x-ui.chip :href="route('tournament.index', ['ansicht' => 'gruppe'])" :active="$view === 'gruppe'">{{ __('tournament::overview.view_groups') }}</x-ui.chip>
            <x-ui.chip :href="route('tournament.index', ['ansicht' => 'ko'])" :active="$view === 'ko'">{{ __('tournament::overview.view_ko') }}</x-ui.chip>
        </div>
    @endif
</div>

@if ($view === 'ko')
    @if (! $bracket['phases'])
        <p class="text-ink-2">{{ __('tournament::overview.ko_not_drawn') }}</p>
    @endif

    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        @foreach ($bracket['phases'] as $phaseInfo)
            <x-ui.card as="article">
                <div class="flex flex-col gap-2.5">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="font-display text-display-s text-ink">
                            @if (Route::has('tournament.bracket'))
                                <a class="hover:text-clay-hover" href="{{ route('tournament.bracket', ['phase' => $phaseInfo['key']]).($phaseInfo['round'] ? '#runde-'.$phaseInfo['round'] : '') }}">{{ $phaseInfo['label'] }}</a>
                            @else
                                {{ $phaseInfo['label'] }}
                            @endif
                            @if ($phaseInfo['roundLabel'])
                                <span class="font-body text-body-s text-ink-2">· {{ $phaseInfo['roundLabel'] }}</span>
                            @endif
                        </h3>
                        <span class="text-body-s text-ink-2">{{ __('tournament::overview.progress', ['played' => (int) $phaseInfo['counts']['played'], 'total' => (int) $phaseInfo['counts']['total']]) }}</span>
                    </div>
                    @include('tournament::partials.progress', ['played' => (int) $phaseInfo['counts']['played'], 'total' => (int) $phaseInfo['counts']['total']])
                </div>
            </x-ui.card>
        @endforeach
    </div>

    @foreach ($placements as $phasePlacement)
        <x-ui.card tone="highlight" as="div" :eyebrow="__('tournament::overview.winner', ['phase' => $phasePlacement['label']])">
            <p class="font-display text-display-m">{{ $name($phasePlacement['ranks'][0]['tpids'][0]) }}</p>
        </x-ui.card>
        <x-ui.card padding="none" :title="count($placements) > 1 ? __('tournament::overview.placements_of', ['phase' => $phasePlacement['label']]) : __('tournament::overview.placements')">
            <x-ui.table :caption="__('tournament::overview.placements')" :stack="false">
                @foreach ($phasePlacement['ranks'] as $row)
                    <tr>
                        <td class="w-12 tabular-nums font-bold">{{ (int) $row['rank'] }}.</td>
                        <td>
                            {{ implode(', ', array_map($name, $row['tpids'])) }}
                            @if (count($row['tpids']) > 1)
                                <span class="text-ink-2">{{ __('tournament::overview.ex_aequo') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endforeach
@else
    @if (! $progress)
        <p class="text-ink-2">{{ __('tournament::overview.no_groups') }}</p>
    @endif

    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($progress as $key => $group)
            <x-ui.card as="article" padding="none">
                <div class="flex flex-col gap-2.5 px-4 pb-2 pt-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="font-display text-display-s text-ink">
                            @if (Route::has('tournament.groups'))
                                <a class="hover:text-clay-hover" href="{{ route('tournament.groups', ['group' => $key]) }}">{{ __('tournament::overview.group', ['group' => $key]) }}</a>
                            @else
                                {{ __('tournament::overview.group', ['group' => $key]) }}
                            @endif
                        </h3>
                        <span class="text-body-s text-ink-2">{{ __('tournament::overview.progress', ['played' => (int) $group['played'], 'total' => (int) $group['total']]) }}</span>
                    </div>
                    @include('tournament::partials.progress', ['played' => (int) $group['played'], 'total' => (int) $group['total']])
                </div>
                @if ($tables[$key])
                    <x-ui.table :caption="__('tournament::overview.group', ['group' => $key])" :stack="false">
                        <x-slot:head>
                            <tr>
                                <th scope="col">{{ __('tournament::overview.col_player') }}</th>
                                <th scope="col" class="text-center">{{ __('tournament::overview.col_points') }}</th>
                                <th scope="col" class="text-center">{{ __('tournament::overview.col_played') }}</th>
                                <th scope="col" class="text-center">{{ __('tournament::overview.col_sets') }}</th>
                            </tr>
                        </x-slot:head>
                        @foreach ($tables[$key] as $row)
                            <tr @class(['bg-clay-soft' => $access->tpid !== null && (int) $row['tpid'] === $access->tpid])>
                                <td>{{ (int) $row['rank'] }}. {{ $row['label'] }}</td>
                                <td class="text-center font-bold tabular-nums">{{ (int) $row['points'] }}</td>
                                <td class="text-center tabular-nums">{{ (int) $row['played'] }}</td>
                                <td class="text-center tabular-nums">{{ $signed($row['scored_set_diff']) }}</td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @else
                    <p class="px-4 pb-4 text-body-s text-ink-2">{{ __('tournament::overview.not_drawn') }}</p>
                @endif
            </x-ui.card>
        @endforeach
    </div>

    <h2 class="font-display text-display-m text-ink">{{ __('tournament::overview.promotion_title') }}</h2>
    <x-ui.notice tone="neutral">{{ __('tournament::overview.promotion_info') }}</x-ui.notice>
    @php
        $lotNeeded = collect($promotion['seconds'])->contains(fn (array $s): bool => ! empty($s['needs_lot']));
    @endphp
    @if ($promotion['winners'] || $promotion['seconds'] || $promotion['pending'])
        <ul class="flex flex-wrap gap-2" aria-label="{{ __('tournament::overview.promotion_title') }}">
            @foreach ($promotion['winners'] as $winner)
                <li><x-ui.badge tone="good">{{ $winner['label'] }} · {{ __('tournament::overview.promotion_group', ['group' => $winner['grp']]) }}</x-ui.badge></li>
            @endforeach
            @foreach (array_slice($promotion['seconds'], 0, (int) $promotion['seconds_count']) as $second)
                <li>
                    <x-ui.badge tone="neutral">{{ $second['label'] }} · {{ __('tournament::overview.promotion_second', ['group' => $second['grp']]) }}@if (! empty($second['dropped_opponent_label'])) {{ __('tournament::overview.promotion_dropped', ['name' => $second['dropped_opponent_label']]) }}@endif</x-ui.badge>
                </li>
            @endforeach
            @foreach ($promotion['pending'] as $pendingGroup)
                <li><x-ui.badge tone="attention">{{ __('tournament::overview.promotion_pending', ['group' => $pendingGroup]) }}</x-ui.badge></li>
            @endforeach
        </ul>
    @endif
    @if ($lotNeeded)
        <x-ui.notice tone="attention">{{ __('tournament::overview.promotion_lot') }}</x-ui.notice>
    @endif

    <p class="text-body-s text-ink-2">{{ __('tournament::overview.itn_default', ['itn' => number_format((float) $tournament->itn_default, 1, ',', '')]) }}</p>
@endif
@endsection
