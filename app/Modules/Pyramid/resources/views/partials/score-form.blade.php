{{--
    Score entry for a scheduled challenge: header row with both players (abbreviated like "H. Mayer"), Satz 1, Satz 2 and the
    match tiebreak, then the sticky save bar. Side a is always the challenger.
    Needs: $challenge, $challenger, $opponent, $scoreHint (ScoreService::describe of the kept input, or null).
--}}
@php
    use App\Modules\Pyramid\Support\PyramidFormat;

    // Never the last word of a label (that can be a number or a suffix): the abbreviated name, full name as tooltip.
    $nameA = PyramidFormat::shortName((string) $challenger->label);
    $nameB = PyramidFormat::shortName((string) $opponent->label);
    $rows = [
        ['label' => __('pyramid::ui.detail.set', ['n' => 1]), 'a' => 's1a', 'b' => 's1b', 'max' => 7, 'sub' => null],
        ['label' => __('pyramid::ui.detail.set', ['n' => 2]), 'a' => 's2a', 'b' => 's2b', 'max' => 7, 'sub' => null],
        ['label' => __('pyramid::ui.detail.tiebreak'), 'a' => 'mtba', 'b' => 'mtbb', 'max' => null, 'sub' => __('pyramid::ui.detail.tiebreak_only')],
    ];
    $hintTone = ['error' => 'problem', 'warning' => 'attention', 'success' => 'good'][$scoreHint['state'] ?? ''] ?? 'neutral';
@endphp
{{--
    Live check (Alpine): every change asks the score-check route, which runs the validator of the submit; only x-text
    reaches the page. Without scripts nothing of it shows and the submit validates as before.
--}}
<form
    method="POST"
    action="{{ route('pyramide.challenge.result', ['challengeId' => (int) $challenge->id]) }}"
    class="flex flex-col gap-4"
    x-data="{
        url: @js(route('pyramide.challenge.score-check', ['challengeId' => (int) $challenge->id])),
        state: '',
        message: '',
        ticket: 0,
        async check(form) {
            const params = new URLSearchParams();
            let filled = false;
            for (const key of ['s1a', 's1b', 's2a', 's2b', 'mtba', 'mtbb']) {
                const value = (form.elements[key]?.value ?? '').trim();
                params.set(key, value);
                filled = filled || value !== '';
            }
            const ticket = ++this.ticket;
            if (!filled) {
                this.state = '';
                this.message = '';
                return;
            }
            try {
                const response = await fetch(this.url + '?' + params.toString(), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const data = response.ok ? await response.json() : null;
                if (ticket !== this.ticket) { return; }
                this.state = data?.state === 'empty' ? '' : (data?.state ?? '');
                this.message = data?.message ?? '';
            } catch (error) {
                if (ticket === this.ticket) {
                    this.state = '';
                    this.message = '';
                }
            }
        },
    }"
    x-on:input.debounce.350ms="check($el)"
>
    @csrf

    <div class="grid grid-cols-3 items-center gap-2">
        <span></span>
        <span class="break-words text-center text-body-s font-bold text-ink-2" title="{{ $challenger->label }}">{{ $nameA }}</span>
        <span class="break-words text-center text-body-s font-bold text-ink-2" title="{{ $opponent->label }}">{{ $nameB }}</span>

        @foreach ($rows as $row)
            <span class="flex flex-col font-bold leading-tight">
                {{ $row['label'] }}
                @if ($row['sub'] !== null)
                    <span class="text-body-s font-medium text-ink-3">{{ $row['sub'] }}</span>
                @endif
            </span>
            <x-ui.input type="number" variant="score" :name="$row['a']" id="pyramid-{{ $row['a'] }}" min="0" :max="$row['max']" :aria-label="__('pyramid::ui.detail.score_aria', ['label' => $row['label'], 'name' => $nameA])" />
            <x-ui.input type="number" variant="score" :name="$row['b']" id="pyramid-{{ $row['b'] }}" min="0" :max="$row['max']" :aria-label="__('pyramid::ui.detail.score_aria', ['label' => $row['label'], 'name' => $nameB])" />
        @endforeach
    </div>

    <p class="text-body-s text-ink-2">{{ __('pyramid::ui.detail.result_help', ['name' => $challenger->label]) }}</p>

    @if ($scoreHint !== null && ! empty($scoreHint['message']))
        {{-- The explanation after a refused submit; it steps aside as soon as the live check has something to say. --}}
        <div x-show="state === ''">
            <x-ui.notice :tone="$hintTone">{{ __($scoreHint['message'], $scoreHint['replace'] ?? []) }}</x-ui.notice>
        </div>
    @endif

    {{-- Always in the page so screen readers hear every change; empty until the first check. --}}
    <div aria-live="polite" aria-atomic="true">
        <p class="ds-notice" x-cloak x-show="state !== '' && message !== ''" x-bind:class="{ 'ds-notice--good font-bold': state === 'valid', 'ds-notice--problem': state === 'invalid', 'ds-notice--neutral': state === 'incomplete' }" x-text="message"></p>
    </div>

    <div class="sticky bottom-0 border-t border-line bg-surface py-3 md:static md:border-0 md:py-0">
        <x-ui.button type="submit">{{ __('pyramid::ui.detail.result_submit') }}</x-ui.button>
    </div>
</form>
