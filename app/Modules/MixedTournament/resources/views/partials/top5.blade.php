{{--
    "Live-Wertung · Top 5" side card of a round page.
    Needs: $top5 (first five standings rows), $labels, $event; optional $standingsUrl (default: player standings).
--}}
@php
    use App\Modules\MixedTournament\Support\MixedFormat;
@endphp
@if (! empty($top5))
<x-ui.card :title="__('mixed::common.top5_title')" class="flex-[1_1_300px]">
    <ol class="flex flex-col">
        @foreach ($top5 as $index => $row)
            <li class="grid grid-cols-[28px_minmax(0,1fr)_auto] items-center gap-2 border-t border-line-soft py-2">
                <span class="font-display text-display-s text-ink-3">{{ $index + 1 }}</span>
                <span class="truncate font-semibold text-ink">{{ $labels[(int) $row['id']] ?? __('mixed::common.unknown_player') }}</span>
                <span class="text-body-s tabular-nums text-ink-2">{{ __('mixed::common.avg_points', ['avg' => MixedFormat::decimal($row['points_avg'])]) }}</span>
            </li>
        @endforeach
    </ol>
    <a href="{{ $standingsUrl ?? route('mixed.standings', ['event' => (int) $event->id]) }}" class="mt-2 inline-flex min-h-touch-min items-center font-semibold text-clay underline hover:text-clay-hover">{{ __('mixed::common.full_table') }}</a>
</x-ui.card>
@endif
