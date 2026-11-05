{{--
    Mixed match card (DESIGN "Match card", Mixed variant): context label ("Gruppe 2 · Platz 1 – Alpha · Welle 1"),
    "A & B" against "C & D" centred, the score or "noch offen".
    Needs: $match (MixedMatch), $labels (participant id => label), $context.
    Optional: $withResults (admin scoring) adds the result buttons (mixed::partials.score-buttons).
--}}
@php
    $unknown = __('mixed::common.unknown_player');
    $name = static fn ($id): string => $labels[(int) $id] ?? $unknown;
    $teamA = __('mixed::common.team', ['a' => $name($match->team_a_p1), 'b' => $name($match->team_a_p2)]);
    $teamB = __('mixed::common.team', ['a' => $name($match->team_b_p1), 'b' => $name($match->team_b_p2)]);
    $played = $match->games_a !== null && $match->games_b !== null;
    $current = $played ? $match->games_a.':'.$match->games_b : null;
@endphp
<x-ui.card as="article">
    <div class="flex flex-col gap-2.5">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <p class="text-label uppercase text-ink-3">{{ $context }}</p>
            @unless ($played)
                <x-ui.badge tone="attention">{{ __('mixed::common.still_open') }}</x-ui.badge>
            @endunless
        </div>
        <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2.5 text-center">
            <span class="font-bold text-ink">{{ $teamA }}</span>
            @if ($played)
                <span class="rounded-md bg-ground px-2.5 py-0.5 font-display text-display-m tabular-nums text-ink"><span class="sr-only">{{ __('mixed::common.result') }} </span>{{ $current }}</span>
            @else
                <span class="text-label uppercase text-ink-3">{{ __('mixed::common.vs') }}</span>
            @endif
            <span class="font-bold text-ink">{{ $teamB }}</span>
        </div>
        @if (! empty($withResults))
            @include('mixed::partials.score-buttons', ['match' => $match, 'current' => $current])
        @endif
    </div>
</x-ui.card>
