{{--
    Match page (old match/edit.phtml): date and result of one match. No own mockup: the header card follows
    Turnier-Meine-Spiele-Mobil (context, badge, players, meta), the entry card follows Turnier-Forderung-Mobil
    (score grid with one row per set, the live hint under it, primary save button).

    The live validator is comfort only; the server (MatchResultService + ScoreService) decides. Its texts are
    the server's ScoreService::describe() texts handed over as JSON ($validator), never re-worded here.
    Write access ($canWrite, $mayEdit, $mayConfirm, $mayDelete) comes from MatchWriteRule in the controller.
--}}
@extends('tournament::layout')

@section('title', __('tournament::match.title'))
@section('eyebrow', $tournament->title)

@section('tournament-content')
@php
    $winner = $match['winner'] !== null ? (int) $match['winner'] : null;
    $woSide = ['wo_a' => 'a', 'wo_b' => 'b'][$match['retired']] ?? null;
    $tone = ['error' => 'problem', 'warning' => 'attention', 'success' => 'good', 'neutral' => 'neutral'][$validator['state']] ?? 'neutral';
    $rows = [
        ['label' => __('tournament::match.set', ['n' => 1]), 'a' => 's1a', 'b' => 's1b', 'sub' => null],
        ['label' => __('tournament::match.set', ['n' => 2]), 'a' => 's2a', 'b' => 's2b', 'sub' => null],
        ['label' => __('tournament::match.tiebreak'), 'a' => 'mtba', 'b' => 'mtbb', 'sub' => __('tournament::match.tiebreak_only')],
    ];
    $readonlyKey = match (true) {
        ! $canWrite && $simulation !== null => 'simulation',
        ! $canWrite && ! $isOwn => 'not_own',
        ! $canWrite => 'other',
        ! $mayEdit => 'locked',
        default => null,
    };
    $live = [
        'messages' => $validator['messages'],
        'valid' => $validator['valid'],
        'walkover' => $validator['walkover'],
        'input' => $validator['input'],
        'date' => (string) $match['played'],
        'time' => $match['played_time'] ? substr((string) $match['played_time'], 0, 5) : '',
        'locked' => ! $mayEdit,
        'tone' => $tone,
        'text' => $validator['text'],
    ];
@endphp

<script>
    // Live hint while typing: the rules of ScoreService::describe(), the texts from the server.
    window.tournamentScoreForm = window.tournamentScoreForm || function (config) {
        const fields = ['s1a', 's1b', 's2a', 's2b', 'mtba', 'mtbb'];
        const toInt = (value) => (/^[0-9]{1,2}$/.test(String(value ?? '').trim()) ? parseInt(value, 10) : null);
        const validSet = (a, b) => {
            if (a === null || b === null || a > 7 || b > 7 || a === b) { return false; }
            const hi = Math.max(a, b), lo = Math.min(a, b);
            return (hi === 6 && lo <= 4) || (hi === 7 && (lo === 5 || lo === 6));
        };
        const validMtb = (a, b) => {
            if (a === null || b === null || a === b) { return false; }
            const hi = Math.max(a, b), lo = Math.min(a, b);
            if (hi < 10) { return false; }
            return hi === 10 ? lo <= 8 : (hi - lo) === 2;
        };
        const tones = { error: 'problem', warning: 'attention', success: 'good', neutral: 'neutral' };
        const say = (state, text) => ({ tone: tones[state], text });

        return {
            locked: config.locked,
            v: Object.assign({}, config.input),
            wo: config.input.wo,
            date: config.date,
            time: config.time,
            digits(field, el) {
                el.value = el.value.replace(/[^0-9]/g, '').slice(0, 2);
                this.v[field] = el.value;
            },
            get hint() {
                const m = config.messages;
                if (this.locked) { return { tone: config.tone, text: config.text }; }
                if (this.wo === 'a' || this.wo === 'b') { return say('success', config.walkover[this.wo]); }
                const n = {};
                fields.forEach((f) => { n[f] = toInt(this.v[f]); });
                if (fields.every((f) => n[f] === null)) {
                    return (this.date !== '' && this.time !== '') ? say('success', m.schedule_only) : say('neutral', m.enter_sets);
                }
                if (!validSet(n.s1a, n.s1b)) { return say('error', m.set1_invalid); }
                if (!validSet(n.s2a, n.s2b)) { return say('error', m.set2_invalid); }
                const w1 = n.s1a > n.s1b ? 'a' : 'b';
                const w2 = n.s2a > n.s2b ? 'a' : 'b';
                const hasMtb = n.mtba !== null || n.mtbb !== null;
                if (w1 === w2) { return hasMtb ? say('error', m.mtb_must_be_empty) : say('success', config.valid[w1]); }
                if (!hasMtb) { return say('warning', m.mtb_needed); }
                if (!validMtb(n.mtba, n.mtbb)) { return say('error', m.mtb_invalid); }
                return say('success', config.valid[n.mtba > n.mtbb ? 'a' : 'b']);
            },
        };
    };
</script>
@if ($mayEdit)
    @include('mixed::partials.scroll-restore', ['scrollId' => (int) $match['tmid']])
@endif

<div class="mx-auto flex w-full max-w-2xl flex-col gap-4">
    <x-ui.card as="article">
        <div class="flex flex-col gap-2.5">
            <div class="flex items-start justify-between gap-2">
                <p class="text-label uppercase text-ink-3">{{ $card['context'] }}</p>
                <x-ui.badge :tone="$card['badge']['group']">{{ $card['badge']['label'] }}</x-ui.badge>
            </div>
            <h2 class="break-words font-display text-display-l leading-none text-ink">{{ $labelA }} – {{ $labelB }}</h2>
            <p class="font-semibold">{{ $card['meta'] ?? __('tournament::match.no_date') }}</p>

            @if ($winner !== null)
                <div class="flex flex-col gap-1 border-t border-line pt-2.5">
                    <p class="text-label uppercase text-ink-3">{{ __('tournament::match.entered_result') }}</p>
                    <p class="text-display-s font-bold tabular-nums text-ink">
                        {{ $match['score'] }}
                        @if ($woSide !== null)
                            <span class="text-body-s font-semibold text-warn-ink">· {{ __('tournament::match.walkover_note', ['name' => $woSide === 'a' ? $labelA : $labelB]) }}</span>
                        @endif
                    </p>
                    <p class="text-body-s text-ink-2">{{ __('tournament::match.winner_line', [
                        'name' => $winner === (int) $match['tpid_a'] ? $labelA : $labelB,
                        'sets' => (int) $match['sets_a'].':'.(int) $match['sets_b'],
                        'games' => (int) $match['games_a'].':'.(int) $match['games_b'],
                    ]) }}</p>
                    <p class="text-body-s">
                        @if ($match['confirmed_uid'])
                            <span class="font-semibold text-court-ink">{{ __('tournament::match.confirmed') }}</span>
                        @else
                            <span class="font-semibold text-warn-ink">{{ __('tournament::match.not_confirmed') }}</span>
                        @endif
                    </p>
                </div>
            @endif

            @if ($whatsapp !== null)
                <x-ui.button variant="secondary" :href="$whatsapp['url']" target="_blank" rel="noopener">{{ $whatsapp['label'] }}</x-ui.button>
            @endif
        </div>
    </x-ui.card>

    @if ($isTest && $simulation !== null && $canWrite)
        <x-ui.notice tone="attention" :title="__('tournament::match.test_write.title')">{{ __('tournament::match.test_write.text', ['label' => $simulation['label']]) }}</x-ui.notice>
    @endif

    @if ($readonlyKey !== null)
        <x-ui.notice tone="neutral">{{ __('tournament::match.readonly.'.$readonlyKey) }}</x-ui.notice>
    @endif

    <x-ui.card :title="__('tournament::match.form_title')" level="2">
        <form method="POST" action="{{ route('tournament.match.save', ['tmid' => (int) $match['tmid']]) }}" class="flex flex-col gap-4" data-scroll-save
              x-data="tournamentScoreForm({{ \Illuminate\Support\Js::from($live) }})">
            @csrf

            <div class="grid grid-cols-2 gap-2">
                <x-ui.field for="match-date" :label="__('tournament::match.date')">
                    <x-ui.input type="date" name="date" id="match-date" :value="(string) $match['played']" :disabled="! $mayEdit" x-on:input="date = $el.value" />
                </x-ui.field>
                <x-ui.field for="match-time" :label="__('tournament::match.time')">
                    <x-ui.input type="time" name="time" id="match-time" step="900" :value="$live['time']" :disabled="! $mayEdit" x-on:input="time = $el.value" />
                </x-ui.field>
            </div>

            <x-ui.option-group :legend="__('tournament::match.court')" :columns="3">
                @foreach ($courts as $court => $courtLabel)
                    <x-ui.option-tile name="court" :value="$court" :label="$courtLabel" :checked="(string) $match['court'] === (string) $court" :disabled="! $mayEdit" />
                @endforeach
            </x-ui.option-group>

            <fieldset class="flex flex-col gap-2">
                <legend class="ds-field__label">{{ __('tournament::match.result') }}</legend>
                <div class="grid grid-cols-3 items-center gap-2">
                    <span></span>
                    <span class="break-words text-center text-body-s font-bold text-ink-2">{{ $labelA }}</span>
                    <span class="break-words text-center text-body-s font-bold text-ink-2">{{ $labelB }}</span>

                    @foreach ($rows as $row)
                        <span class="flex flex-col font-bold leading-tight">
                            {{ $row['label'] }}
                            @if ($row['sub'] !== null)
                                <span class="text-body-s font-medium text-ink-3">{{ $row['sub'] }}</span>
                            @endif
                        </span>
                        @foreach (['a' => $labelA, 'b' => $labelB] as $side => $name)
                            <x-ui.input type="text" variant="score" :name="$row[$side]" :id="'match-'.$row[$side]" maxlength="2" autocomplete="off"
                                :value="$input[$row[$side]]" :disabled="! $mayEdit"
                                :aria-label="__('tournament::match.score_aria', ['label' => $row['label'], 'name' => $name])"
                                x-on:input="digits('{{ $row[$side] }}', $el)" x-bind:disabled="locked || wo !== ''" />
                        @endforeach
                    @endforeach
                </div>
            </fieldset>

            {{-- one tile per row on phones: the player names do not fit three columns --}}
            <fieldset class="ds-options">
                <legend class="ds-field__label">{{ __('tournament::match.walkover') }}</legend>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <x-ui.option-tile name="wo" value="" :label="__('tournament::match.regular')" :checked="$input['wo'] === ''" :disabled="! $mayEdit" x-on:change="wo = $el.value" />
                    <x-ui.option-tile name="wo" value="a" :label="__('tournament::match.walkover_side', ['name' => $labelA])" :checked="$input['wo'] === 'a'" :disabled="! $mayEdit" x-on:change="wo = $el.value" />
                    <x-ui.option-tile name="wo" value="b" :label="__('tournament::match.walkover_side', ['name' => $labelB])" :checked="$input['wo'] === 'b'" :disabled="! $mayEdit" x-on:change="wo = $el.value" />
                </div>
            </fieldset>

            <x-ui.notice :tone="$tone" aria-live="polite"
                x-bind:class="{ 'ds-notice--problem': hint.tone === 'problem', 'ds-notice--attention': hint.tone === 'attention', 'ds-notice--good': hint.tone === 'good', 'ds-notice--neutral': hint.tone === 'neutral' }"
                x-text="hint.text">{{ $validator['text'] }}</x-ui.notice>

            @if ($mayEdit)
                <x-ui.button type="submit">{{ $winner !== null ? __('tournament::match.change') : __('tournament::match.save') }}</x-ui.button>
            @endif
        </form>
    </x-ui.card>

    @if ($mayConfirm || $mayDelete)
        <div class="flex flex-wrap gap-2">
            @if ($mayConfirm)
                <form method="POST" action="{{ route('tournament.match.confirm', ['tmid' => (int) $match['tmid']]) }}" data-scroll-save>
                    @csrf
                    <x-ui.button type="submit" variant="secondary" :block="false">{{ __('tournament::match.confirm') }}</x-ui.button>
                </form>
            @endif
            @if ($mayDelete)
                <form method="POST" action="{{ route('tournament.match.delete', ['tmid' => (int) $match['tmid']]) }}" data-scroll-save
                      x-data x-on:submit="if (! window.confirm({{ \Illuminate\Support\Js::from(__('tournament::match.delete_question')) }})) { $event.preventDefault(); }">
                    @csrf
                    <x-ui.button type="submit" variant="danger" :block="false">{{ __('tournament::match.delete') }}</x-ui.button>
                </form>
            @endif
        </div>
    @endif

    <x-ui.button variant="text" :block="false" class="self-start" :href="route('tournament.schedule')">{{ __('tournament::match.back') }}</x-ui.button>
</div>
@endsection
