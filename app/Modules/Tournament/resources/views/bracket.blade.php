{{--
    KO bracket (old index/bracket.phtml, mockup Turnier-KO-Raster): phase chips, all rounds as columns
    (horizontal scroll on phones, each round has the anchor #runde-n), lines in three states: code from the
    draw (Q3, AC5, S1, with its meaning as a title hint), resolved player, or bye/open. Round 1 reads the
    lines, later rounds the players carried into the matches. A match with both players links to the
    match page (as in ZF2).
--}}
@extends('tournament::layout')

@section('tournament-content')
@php
    use App\Modules\Tournament\Support\MatchPresenter;
    use Illuminate\Support\Facades\Route;

    $presenter = app(MatchPresenter::class);
@endphp

@if ($active === null)
    <p class="text-ink-2">{{ __('tournament::bracket.not_drawn') }}</p>
@else
@php
    $lineLabel = function (int $lineNo) use ($lines, $players, $codeMeaning): array {
        if (! isset($lines[$lineNo])) {
            return ['text' => __('tournament::bracket.line.open'), 'muted' => true, 'hint' => null];
        }

        $line = $lines[$lineNo];

        if ($line['kind'] === 'bye') {
            return ['text' => __('tournament::bracket.line.bye'), 'muted' => true, 'hint' => __('tournament::bracket.line.bye_hint')];
        }

        if ($line['tpid'] && isset($players[(int) $line['tpid']])) {
            return ['text' => (string) $players[(int) $line['tpid']]['label'], 'muted' => false, 'hint' => null];
        }

        if ($line['placeholder']) {
            return ['text' => (string) $line['placeholder'], 'muted' => true, 'hint' => $codeMeaning[$line['placeholder']] ?? null];
        }

        if ($line['kind'] === 'seed') {
            $code = 'S'.(int) $line['seed_no'];

            return ['text' => $code, 'muted' => true, 'hint' => $codeMeaning[$code] ?? __('tournament::bracket.line.seed_hint', ['n' => (int) $line['seed_no']])];
        }

        return ['text' => __('tournament::bracket.line.undrawn'), 'muted' => true, 'hint' => __('tournament::bracket.line.undrawn_hint')];
    };
    $playerLabel = fn ($tpid): array => $tpid && isset($players[(int) $tpid])
        ? ['text' => (string) $players[(int) $tpid]['label'], 'muted' => false, 'hint' => null]
        : ['text' => __('tournament::bracket.line.undrawn'), 'muted' => true, 'hint' => null];
    $seeded = count($phase['lines']['seeded'] ?? []);
    $byes = count($phase['lines']['bye'] ?? []);
    $ruleText = fn (string $rule): string => in_array($rule, ['all', 'group_winners', 'best_seconds', 'remainder'], true) ? __('tournament::bracket.rules.'.$rule) : $rule;
@endphp

<div class="flex flex-wrap items-center justify-between gap-3">
    <nav aria-label="{{ __('tournament::bracket.phases_label') }}" class="flex flex-wrap gap-2">
        @foreach ($phases as $key => $item)
            <x-ui.chip :href="route('tournament.bracket', ['phase' => $key])" :active="$key === $active">{{ $item['label'] }}</x-ui.chip>
        @endforeach
    </nav>
    <p class="text-body-s text-ink-2">
        {{ __('tournament::bracket.meta', ['size' => (int) $phase['size'], 'players' => (int) $phase['size'] - $byes, 'seeded' => $seeded]) }} · {{ trans_choice('tournament::bracket.byes', $byes, ['count' => $byes]) }}
        @if (Route::has('draw.index'))
            · <a class="font-bold text-clay underline hover:text-clay-hover" href="{{ route('draw.index') }}">{{ __('tournament::bracket.record') }}</a>
        @endif
    </p>
</div>

<x-ui.card>
    <div class="overflow-x-auto">
        <div class="flex min-w-max items-stretch gap-4">
            @foreach ($rounds as $index => $roundName)
                @php
                    $round = $index + 1;
                    $roundMatches = $matches[$round] ?? [];
                @endphp
                <section id="runde-{{ $round }}" class="flex w-64 shrink-0 flex-col gap-3" aria-label="{{ $roundName }}">
                    <h2 class="text-label uppercase text-ink-3">{{ $roundName }}</h2>
                    @if (! $roundMatches)
                        <p class="text-body-s text-ink-2">{{ __('tournament::bracket.no_round_matches') }}</p>
                    @else
                        <ol class="flex flex-1 flex-col justify-around gap-3">
                            @foreach ($roundMatches as $slot => $match)
                                @php
                                    $sides = $round === 1
                                        ? [$lineLabel(2 * (int) $slot - 1), $lineLabel(2 * (int) $slot)]
                                        : [$playerLabel($match['tpid_a']), $playerLabel($match['tpid_b'])];
                                    $seedNos = $round === 1
                                        ? [($lines[2 * (int) $slot - 1]['kind'] ?? null) === 'seed' ? (int) $lines[2 * (int) $slot - 1]['seed_no'] : null, ($lines[2 * (int) $slot]['kind'] ?? null) === 'seed' ? (int) $lines[2 * (int) $slot]['seed_no'] : null]
                                        : [null, null];
                                    $winner = (int) $match['winner'];
                                    $tpids = [(int) $match['tpid_a'], (int) $match['tpid_b']];
                                    $score = $match['winner'] ? $presenter->score($match) : ['', ''];
                                    $playable = $match['tpid_a'] && $match['tpid_b'];
                                @endphp
                                <li>
                                    <div class="relative rounded-lg border border-line bg-surface">
                                        @foreach ([0, 1] as $i)
                                            <div @class(['flex items-center justify-between gap-2 px-3 py-2', 'border-t border-line' => $i === 1])>
                                                <span class="min-w-0 truncate">
                                                    <span @class(['text-ink-3' => $sides[$i]['muted'], 'font-bold text-ink' => ! $sides[$i]['muted'] && $winner && $winner === $tpids[$i], 'text-ink' => ! $sides[$i]['muted'] && ! ($winner && $winner === $tpids[$i]), 'underline decoration-dotted' => $sides[$i]['hint'] !== null]) @if ($sides[$i]['hint'] !== null) title="{{ $sides[$i]['hint'] }}" @endif>{{ $sides[$i]['text'] }}</span>
                                                    @if ($seedNos[$i] !== null)
                                                        <span class="text-body-s text-ink-2">· {{ __('tournament::bracket.line.seeded', ['n' => $seedNos[$i]]) }}</span>
                                                    @endif
                                                </span>
                                                @if ($score[$i] !== '')
                                                    <span @class(['whitespace-nowrap tabular-nums', 'font-bold' => $winner && $winner === $tpids[$i]])>{{ $score[$i] }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                        @if ($playable)
                                            <a class="absolute inset-0 rounded-lg hover:ring-2 hover:ring-line-strong" href="{{ route('tournament.match', ['tmid' => (int) $match['tmid']]) }}"><span class="sr-only">{{ __('tournament::bracket.open_match', ['a' => $sides[0]['text'], 'b' => $sides[1]['text']]) }}</span></a>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            @endforeach
        </div>
    </div>
</x-ui.card>

@if ($legend)
    <x-ui.disclosure :summary="__('tournament::bracket.legend_title')">
        <div class="flex flex-col gap-3">
            <p class="text-body-s text-ink-2">{{ __('tournament::bracket.legend_intro') }}</p>
            <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
                @foreach ($legend as $entry)
                    <dt class="whitespace-nowrap font-bold tabular-nums">{{ $entry['code'] }}</dt>
                    <dd>{{ $entry['text'] }}</dd>
                @endforeach
            </dl>
            <p class="text-body-s text-ink-2"><strong>{{ __('tournament::bracket.legend_rank_title') }}</strong> {{ __('tournament::bracket.legend_rank') }}</p>
            <p class="text-body-s text-ink-2">{{ __('tournament::bracket.legend_seed') }}</p>
        </div>
    </x-ui.disclosure>
@endif

<x-ui.card :title="__('tournament::bracket.qualification')">
    <ul class="flex flex-col divide-y divide-line">
        @foreach ($phase['qualify'] ?? [] as $rule)
            <li class="flex justify-between gap-3 py-2">
                <span class="text-ink-2">{{ __('tournament::bracket.from', ['phase' => $rule['from']]) }}</span>
                <span>{{ $ruleText((string) $rule['rule']) }}@if (isset($rule['count'])) ({{ (int) $rule['count'] }})@endif</span>
            </li>
        @endforeach
    </ul>
    <p class="mt-3 text-body-s text-ink-2">
        {{ __('tournament::bracket.footnote') }}
        @if (Route::has('draw.index'))
            {{ __('tournament::bracket.footnote_record') }} <a class="font-bold text-clay underline hover:text-clay-hover" href="{{ route('draw.index') }}">{{ __('tournament::bracket.record') }}</a>.
        @endif
    </p>
</x-ui.card>
@endif
@endsection
