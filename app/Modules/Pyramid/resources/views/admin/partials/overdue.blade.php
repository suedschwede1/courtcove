{{-- Overdue challenges with the three ways out. Needs: $overdue (list of challenges), $labels, $suggestedDeadline. --}}
@php
    use App\Modules\Pyramid\Support\PyramidFormat;
    use App\Modules\Pyramid\Support\PyramidLinks;

    $decisions = [
        'extend' => __('pyramid::admin.ladder.overdue.extend'),
        'forfeit_opponent' => __('pyramid::admin.ladder.overdue.forfeit_opponent'),
        'forfeit_challenger' => __('pyramid::admin.ladder.overdue.forfeit_challenger'),
        'expired' => __('pyramid::admin.ladder.overdue.expired'),
    ];
@endphp
<x-ui.card :title="__('pyramid::admin.ladder.overdue.title', ['count' => count($overdue)])" class="bg-warn-soft">
    <div class="flex flex-col gap-4">
        <p class="text-ink-2">{{ __('pyramid::admin.ladder.overdue.intro') }}</p>

        @foreach ($overdue as $challenge)
            <form method="POST" action="{{ PyramidLinks::adminChallengeAction('override-challenge', 'override', (int) $challenge->id) }}" class="flex flex-col gap-3">
                @csrf
                <p class="font-semibold text-ink">{{ __('pyramid::admin.ladder.overdue.line', [
                    'challenger' => $labels[(int) $challenge->challenger_id] ?? __('pyramid::admin.ladder.unknown'),
                    'opponent' => $labels[(int) $challenge->opponent_id] ?? __('pyramid::admin.ladder.unknown'),
                    'date' => PyramidFormat::safeDate($challenge->deadline),
                ]) }}</p>
                <div class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.field :for="'pyramid-od-'.$challenge->id" :label="__('pyramid::admin.ladder.overdue.decision')">
                        <x-ui.select name="decision" :id="'pyramid-od-'.$challenge->id" :options="$decisions" selected="extend" />
                    </x-ui.field>
                    <x-ui.field :for="'pyramid-nd-'.$challenge->id" :label="__('pyramid::admin.ladder.overdue.new_deadline')" :help="__('pyramid::admin.ladder.overdue.new_deadline_help')">
                        <x-ui.input type="date" name="new_deadline" :id="'pyramid-nd-'.$challenge->id" :value="$suggestedDeadline" />
                    </x-ui.field>
                    <x-ui.field :for="'pyramid-on-'.$challenge->id" :label="__('pyramid::admin.ladder.overdue.note')">
                        <x-ui.input name="admin_note" :id="'pyramid-on-'.$challenge->id" :value="''" required />
                    </x-ui.field>
                    <x-ui.button type="submit" variant="dark">{{ __('pyramid::admin.ladder.overdue.submit') }}</x-ui.button>
                </div>
            </form>
        @endforeach
    </div>
</x-ui.card>
