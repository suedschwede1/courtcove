{{--
    "Prüfung des Vorschlags" (old round.phtml, proposed rounds only): match count, repeated encounters (from
    the second earlier shared group on, one list), avoid-pair conflicts (event admins only: the page is also
    open to scorers) and "3 Plätze -> 2 Wellen" with an arrow icon.
    Needs: $preview (RoundService::preview()), $byGroup, $labels, $courtCount, $waveCount, $canAdmin.
--}}
@php
    $unknown = __('mixed::common.unknown_player');
    $name = static fn ($id): string => $labels[(int) $id] ?? $unknown;
    $matchTotal = array_sum(array_map('count', $byGroup));
    $repeated = $preview['repeated_encounters'];
    $collisions = $preview['avoid_collisions'];
@endphp
<x-ui.card :title="__('mixed::admin.round.check_title')">
    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
            <span><strong>{{ __('mixed::admin.round.match_count') }}</strong> <span class="tabular-nums">{{ $matchTotal }}</span></span>
            <span><strong>{{ __('mixed::admin.round.repeats') }}</strong> <span class="tabular-nums">{{ count($repeated) }}</span></span>
            @if ($canAdmin)
                <span class="inline-flex flex-wrap items-center gap-1.5">
                    <strong>{{ __('mixed::admin.round.avoid') }}</strong>
                    @if ($collisions === [])
                        <x-ui.badge tone="good">{{ __('mixed::admin.round.avoid_none') }}</x-ui.badge>
                    @else
                        <x-ui.badge tone="problem">{{ trans_choice('mixed::admin.round.avoid_conflicts', count($collisions), ['count' => count($collisions)]) }}</x-ui.badge>
                    @endif
                </span>
            @endif
        </div>

        <p class="inline-flex flex-wrap items-center gap-1.5 text-body-s text-ink-2">
            <span>{{ trans_choice('mixed::admin.round.courts', $courtCount, ['count' => $courtCount]) }}</span>
            <x-ui.icon name="arrow-right" :size="16" />
            <span class="sr-only">,</span>
            <span>{{ trans_choice('mixed::admin.round.waves', $waveCount, ['count' => $waveCount]) }}</span>
        </p>
        @if ($waveCount > 1)
            <p class="text-body-s text-ink-2">{{ __('mixed::admin.round.waves_rule') }}</p>
        @endif

        @if ($repeated !== [])
            <ul class="flex flex-col gap-1 text-body-s text-ink-2">
                @foreach ($repeated as $pair)
                    <li>{{ __('mixed::admin.round.repeat_line', ['a' => $name($pair['players'][0]), 'b' => $name($pair['players'][1]), 'count' => (int) $pair['previous']]) }}</li>
                @endforeach
            </ul>
        @endif

        @if ($canAdmin && $collisions !== [])
            <x-ui.notice tone="attention" :title="__('mixed::admin.round.avoid_warning')">
                <ul class="flex flex-wrap gap-x-4 gap-y-1">
                    @foreach ($collisions as $pair)
                        <li class="inline-flex items-center gap-1.5">
                            <span aria-hidden="true">{{ $name($pair[0]) }}</span>
                            <x-ui.icon name="arrows-left-right" :size="16" />
                            <span aria-hidden="true">{{ $name($pair[1]) }}</span>
                            <span class="sr-only">{{ __('mixed::admin.round.avoid_pair', ['a' => $name($pair[0]), 'b' => $name($pair[1])]) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-ui.notice>
        @endif

        <details class="text-body-s text-ink-2">
            <summary class="inline-flex min-h-touch-min cursor-pointer items-center font-semibold text-ink">{{ __('mixed::admin.round.match_count_players') }}</summary>
            <ul class="flex flex-wrap gap-x-4 gap-y-1 pt-1">
                @foreach ($preview['match_counts'] as $pid => $count)
                    <li>{{ __('mixed::admin.round.match_count_line', ['name' => $name($pid), 'count' => (int) $count]) }}</li>
                @endforeach
            </ul>
        </details>
    </div>
</x-ui.card>
