{{-- Correct recorded results (the ranking is not recalculated). Needs: $resolved, $labels. --}}
@php
    use App\Modules\Pyramid\Support\PyramidFormat;
    use App\Modules\Pyramid\Support\PyramidLinks;
@endphp
<x-ui.card :title="__('pyramid::admin.ladder.corrections.title')">
    <div class="flex flex-col gap-5">
        <p class="text-ink-2">{{ __('pyramid::admin.ladder.corrections.hint') }}</p>

        @foreach ($resolved as $challenge)
            @php
                $challengerId = (int) $challenge->challenger_id;
                $opponentId = (int) $challenge->opponent_id;
                $challengerLabel = $labels[$challengerId] ?? __('pyramid::admin.ladder.unknown');
                $opponentLabel = $labels[$opponentId] ?? __('pyramid::admin.ladder.unknown');
            @endphp
            <form method="POST" action="{{ PyramidLinks::adminChallengeAction('correct-result', 'correct-result', (int) $challenge->id) }}" class="flex flex-col gap-3 border-t border-line pt-4 first:border-t-0 first:pt-0">
                @csrf
                <p class="font-semibold text-ink">{{ __('pyramid::admin.ladder.corrections.line', [
                    'date' => PyramidFormat::safeDate($challenge->resolved),
                    'challenger' => $challengerLabel,
                    'opponent' => $opponentLabel,
                ]) }}</p>
                <div class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.field :for="'pyramid-cs-'.$challenge->id" :label="__('pyramid::admin.ladder.corrections.score')">
                        <x-ui.input name="score" :id="'pyramid-cs-'.$challenge->id" :value="(string) $challenge->score" :placeholder="__('pyramid::admin.ladder.corrections.score_placeholder')" />
                    </x-ui.field>
                    <x-ui.field :for="'pyramid-cw-'.$challenge->id" :label="__('pyramid::admin.ladder.corrections.winner')">
                        <x-ui.select name="winner_id" :id="'pyramid-cw-'.$challenge->id" :options="[$challengerId => $challengerLabel, $opponentId => $opponentLabel]" :selected="(string) $challenge->winner_id" />
                    </x-ui.field>
                    <x-ui.field :for="'pyramid-cn-'.$challenge->id" :label="__('pyramid::admin.ladder.corrections.note')">
                        <x-ui.input name="admin_note" :id="'pyramid-cn-'.$challenge->id" :value="''" required />
                    </x-ui.field>
                    <x-ui.button type="submit" variant="dark">{{ __('pyramid::admin.ladder.corrections.submit') }}</x-ui.button>
                </div>
            </form>
        @endforeach
    </div>
</x-ui.card>
