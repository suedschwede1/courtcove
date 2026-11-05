{{--
    Groups (old index/groups.phtml, repo version): group chips, the live table of one group and its match
    cards. The Sätze/Games columns show the SCORED differences used for the ranking (with tiebreak_scope
    "tied" only the matches among the tied players count). Own row clay-soft, the winner of a complete group
    court-soft, an unresolved lot tie carries the "Los" badge. ITN only for admins.
--}}
@extends('tournament::layout')

@section('tournament-content')
@php
    use App\Modules\Tournament\Support\MatchPresenter;

    $presenter = app(MatchPresenter::class);
    $phaseOf = $active !== null ? ($groups[$active] ?? null) : null;
    $isAc = $phaseOf !== null && $phaseOf['phase'] === 'AC';
    $signed = fn (int|string|null $n): string => sprintf('%+d', (int) $n);
    $byState = ['played' => 0, 'scheduled' => 0, 'open' => 0];
    foreach ($matches as $m) {
        $byState[$presenter->state($m)]++;
    }
@endphp

@if ($groups)
    <nav aria-label="{{ __('tournament::groups.filter_label') }}" class="flex flex-wrap gap-2">
        @foreach ($groups as $key => $info)
            <x-ui.chip :href="route('tournament.groups', ['group' => $key])" :active="$key === $active">{{ __('tournament::groups.group', ['group' => $key]) }}</x-ui.chip>
        @endforeach
    </nav>
@endif

@if ($active === null)
    <p class="text-ink-2">{{ __('tournament::overview.no_groups') }}</p>
@else
<div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-5">
    <x-ui.card padding="none" class="lg:col-span-3" :title="__('tournament::groups.group', ['group' => $active])">
        <div class="flex flex-col gap-1 px-5 pb-3">
            <p class="text-body-s text-ink-2">{{ __('tournament::groups.meta', ['size' => $phaseOf ? (int) $phaseOf['size'] : 0]) }}@if ($phaseOf) · {{ $phaseOf['label'] }}@endif</p>
            <p class="text-body-s text-ink-2">{{ $isAc ? __('tournament::groups.info_ac') : __('tournament::groups.info_default') }}</p>
        </div>

        @if (! $table)
            <p class="px-5 pb-5 text-warn-ink">{{ __('tournament::groups.not_drawn') }}</p>
        @else
            <x-ui.table :caption="__('tournament::groups.group', ['group' => $active])" :stack="false">
                <x-slot:head>
                    <tr>
                        <th scope="col">{{ __('tournament::groups.col_rank') }}</th>
                        <th scope="col">{{ __('tournament::groups.col_player') }}</th>
                        <th scope="col" class="text-center">{{ __('tournament::groups.col_points') }}</th>
                        <th scope="col" class="text-center">{{ __('tournament::groups.col_played') }}</th>
                        <th scope="col" class="text-center">{{ __('tournament::groups.col_sets') }}</th>
                        <th scope="col" class="text-center">{{ __('tournament::groups.col_games') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($table as $row)
                    @php
                        $isMe = $access->tpid !== null && (int) $row['tpid'] === $access->tpid;
                        $rank = (int) $row['rank'];
                        $status = null;
                        if ($complete) {
                            $status = $isAc ? 'qualified' : ($rank === 1 ? 'winner' : ($rank === 2 ? 'possible' : 'ko_b'));
                        }
                        $line = array_filter([
                            $access->isAdmin() ? __('tournament::groups.itn', ['itn' => number_format((float) $row['itn_rated'], 1, ',', '')]) : null,
                            $status !== null ? __('tournament::groups.status.'.$status) : null,
                        ]);
                        $decided = ! empty($row['tied_block']) ? ($row['decided_by'] ?? null) : null;
                        $noteKey = match (true) {
                            $decided === 'lot' && ! empty($row['needs_lot']) => 'lot',
                            $decided === 'lot' => 'lot_drawn',
                            in_array($decided, ['head_to_head', 'set_diff', 'game_diff'], true) => $decided,
                            default => null,
                        };
                    @endphp
                    <tr @class(['bg-clay-soft' => $isMe, 'bg-court-soft' => ! $isMe && $complete && $rank === 1])>
                        <td class="font-display text-display-s tabular-nums">{{ $rank }}</td>
                        <td>
                            <span class="flex flex-col gap-0.5">
                                <span class="flex flex-wrap items-center gap-1.5">
                                    <span class="font-bold text-ink">{{ $row['label'] }}</span>
                                    @if ($isMe)
                                        <x-ui.badge tone="you">{{ __('tournament::groups.you') }}</x-ui.badge>
                                    @endif
                                    @if (! empty($row['needs_lot']))
                                        <x-ui.badge tone="attention">{{ __('tournament::groups.lot_badge') }}</x-ui.badge>
                                    @endif
                                </span>
                                @if ($line)
                                    <span class="text-body-s text-ink-2">{{ implode(' · ', $line) }}</span>
                                @endif
                                @if ($noteKey !== null)
                                    <span @class(['text-body-s', 'text-warn-ink' => $noteKey === 'lot', 'text-ink-2' => $noteKey !== 'lot'])>{{ __('tournament::groups.decided.'.$noteKey) }}</span>
                                @endif
                                @if ($cleanedSecond && $rank === 2)
                                    <span class="text-body-s text-ink-2">{{ __('tournament::groups.cleaned', ['name' => $cleanedSecond['dropped_opponent_label'] ?? '', 'points' => (int) $cleanedSecond['points'], 'sets' => $signed($cleanedSecond['set_diff']), 'games' => $signed($cleanedSecond['game_diff'])]) }}</span>
                                @endif
                            </span>
                        </td>
                        <td class="text-center font-bold tabular-nums">{{ (int) $row['points'] }}</td>
                        <td class="text-center tabular-nums text-ink-2">{{ (int) $row['played'] }}/{{ $phaseOf ? (int) $phaseOf['size'] - 1 : 0 }}</td>
                        <td class="text-center tabular-nums">{{ $signed($row['scored_set_diff']) }}</td>
                        <td class="text-center tabular-nums">{{ $signed($row['scored_game_diff']) }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
            <p class="px-5 py-3 text-body-s text-ink-2">{{ __('tournament::groups.footnote') }}</p>
        @endif
    </x-ui.card>

    <section class="flex flex-col gap-3 lg:col-span-2" aria-labelledby="group-matches">
        <h2 id="group-matches" class="font-display text-display-m text-ink">{{ __('tournament::groups.matches_title') }}</h2>
        @if ($matches)
            <p class="text-body-s text-ink-2">{{ __('tournament::groups.matches_summary', $byState) }}</p>
        @else
            <p class="text-ink-2">{{ __('tournament::groups.no_matches') }}</p>
        @endif
        @foreach ($matches as $match)
            @include('tournament::partials.match-card', ['props' => $presenter->present($match, $players, $access, $tournament)])
        @endforeach
    </section>
</div>
@endif
@endsection
