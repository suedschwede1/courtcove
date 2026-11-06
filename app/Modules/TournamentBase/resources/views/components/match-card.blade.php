{{--
    Tournament match card, singles variant (DESIGN "Match card (tournaments)"): context label and badge on
    top, players stacked with the winner in bold and the score right-aligned in tabular figures, the meta
    line (date · time · court, or "noch kein Termin") and at most one action by status.
    Props: context (string), badge (['group' => good|neutral|attention|problem|you, 'label' => string]),
    players (list of ['name' => string, 'winner' => bool]), score (list of strings, one per player, sets
    separated by spaces), meta (string or null), action (null or ['label', 'url', 'variant' => primary|secondary]).
--}}
@props(['context', 'badge', 'players', 'score' => [], 'meta' => null, 'action' => null])
@php
    $hasWinner = collect($players)->contains(fn (array $p): bool => ! empty($p['winner']));
@endphp
<x-ui.card as="article" {{ $attributes }}>
    <div class="flex flex-col gap-2.5">
        <div class="flex items-start justify-between gap-2">
            <p class="text-label uppercase text-ink-3">{{ $context }}</p>
            <x-ui.badge :tone="$badge['group']">{{ $badge['label'] }}</x-ui.badge>
        </div>
        <div class="flex flex-col gap-0.5">
            @foreach ($players as $i => $player)
                @php
                    $weight = ! empty($player['winner']) ? 'font-bold text-ink' : ($hasWinner ? 'font-medium text-ink-2' : 'font-semibold text-ink');
                @endphp
                <div class="flex items-center justify-between gap-3">
                    <span class="min-w-0 truncate {{ $weight }}">{{ $player['name'] }}</span>
                    @if (($score[$i] ?? '') !== '')
                        <span class="whitespace-nowrap tabular-nums {{ $weight }}">{{ $score[$i] }}</span>
                    @endif
                </div>
            @endforeach
        </div>
        <p class="text-sm text-ink-2">{{ $meta ?? __('tournament-base::common.match.no_date') }}</p>
        @if ($action !== null)
            <x-ui.button :href="$action['url']" :variant="$action['variant'] ?? 'primary'">{{ $action['label'] }}</x-ui.button>
        @endif
    </div>
</x-ui.card>
