{{--
    Standings: Platz, Name, ⌀ Punkte, Punkte, Matches, ⌀ Games. Participants that missed two or more rounds
    stay unnumbered with "nicht gewertet · noch n Runde(n)".
    Wide screens: a table. Phones: one compact row per participant (DESIGN "on phones lists become cards"),
    the stacked table would take five lines per player.
    Needs: $standings (RoundService::standings()), $labels.
--}}
@php
    use App\Modules\MixedTournament\Support\MixedFormat;

    $unknown = __('mixed::common.unknown_player');
@endphp
<x-ui.card padding="none" :title="__('mixed::common.standings_title')">
    <div class="hidden md:block">
        <x-ui.table :caption="__('mixed::common.standings_title')" :stack="false">
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('mixed::common.col_rank') }}</th>
                    <th scope="col">{{ __('mixed::common.col_name') }}</th>
                    <th scope="col" class="text-center">{{ __('mixed::common.col_points_avg') }}</th>
                    <th scope="col" class="text-center">{{ __('mixed::common.col_points') }}</th>
                    <th scope="col" class="text-center">{{ __('mixed::common.col_matches') }}</th>
                    <th scope="col" class="text-center">{{ __('mixed::common.col_games_avg') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($standings as $index => $row)
                <tr>
                    <td class="font-display text-display-s tabular-nums">{{ $row['eligible'] ? $index + 1 : '–' }}</td>
                    <td>
                        <span class="flex flex-col">
                            <span class="font-bold text-ink">{{ $labels[(int) $row['id']] ?? $unknown }}</span>
                            @unless ($row['eligible'])
                                <span class="text-label text-ink-3">{{ trans_choice('mixed::common.not_ranked', (int) $row['rounds_needed'], ['count' => (int) $row['rounds_needed']]) }}</span>
                            @endunless
                        </span>
                    </td>
                    <td class="text-center font-bold tabular-nums">{{ MixedFormat::decimal($row['points_avg']) }}</td>
                    <td class="text-center tabular-nums">{{ (int) $row['points'] }}</td>
                    <td class="text-center tabular-nums">{{ (int) $row['played'] }}</td>
                    <td class="text-center tabular-nums">{{ MixedFormat::decimal($row['games_avg']) }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    </div>

    <ol class="flex flex-col md:hidden">
        @foreach ($standings as $index => $row)
            <li class="flex items-center gap-3 border-t border-line-soft px-4 py-2.5">
                <span class="w-7 shrink-0 font-display text-display-s tabular-nums text-ink">{{ $row['eligible'] ? $index + 1 : '–' }}</span>
                <span class="flex min-w-0 flex-1 flex-col">
                    <span class="truncate font-bold text-ink">{{ $labels[(int) $row['id']] ?? $unknown }}</span>
                    <span class="text-body-s tabular-nums text-ink-2">{{ __('mixed::common.standings_line', ['points' => (int) $row['points'], 'matches' => (int) $row['played'], 'games' => MixedFormat::decimal($row['games_avg'])]) }}</span>
                    @unless ($row['eligible'])
                        <span class="text-label text-ink-3">{{ trans_choice('mixed::common.not_ranked', (int) $row['rounds_needed'], ['count' => (int) $row['rounds_needed']]) }}</span>
                    @endunless
                </span>
                <span class="shrink-0 text-right font-bold tabular-nums text-ink">{{ __('mixed::common.avg_points', ['avg' => MixedFormat::decimal($row['points_avg'])]) }}</span>
            </li>
        @endforeach
    </ol>
</x-ui.card>
