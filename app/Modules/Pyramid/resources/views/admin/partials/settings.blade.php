{{-- Ladder settings. Needs: $ladder, $globalAdmin (only global admins see the default checkbox). --}}
@php
    use App\Modules\Pyramid\Support\PyramidLinks;

    $days = [
        'days_to_play' => (int) $ladder->days_to_play,
        'winner_protected_days' => (int) $ladder->winner_protected_days,
        'loser_cooldown_days' => (int) $ladder->loser_cooldown_days,
    ];
@endphp
<x-ui.card :title="__('pyramid::admin.ladder.settings.title')">
    <form method="POST" action="{{ PyramidLinks::adminLadderAction('config', (int) $ladder->id) }}" class="flex flex-col gap-3">
        @csrf
        <x-ui.field for="pyramid-set-title" :label="__('pyramid::admin.ladder.settings.name')">
            <x-ui.input name="title" id="pyramid-set-title" :value="(string) $ladder->title" maxlength="160" />
        </x-ui.field>
        <x-ui.field for="pyramid-set-category" :label="__('pyramid::admin.ladder.settings.category')">
            <x-ui.input name="category" id="pyramid-set-category" :value="(string) $ladder->category" maxlength="40" />
        </x-ui.field>
        @foreach ($days as $field => $value)
            <x-ui.field :for="'pyramid-set-'.$field" :label="__('pyramid::admin.ladder.settings.'.$field)">
                <x-ui.input type="number" :name="$field" :id="'pyramid-set-'.$field" :value="(string) $value" min="0" max="365" inputmode="numeric" />
            </x-ui.field>
        @endforeach
        @if ($globalAdmin)
            <x-ui.option-group :columns="2">
                <x-ui.option-tile type="checkbox" name="is_default" value="1" :label="__('pyramid::admin.ladder.settings.is_default')" :checked="(bool) $ladder->is_default" />
            </x-ui.option-group>
        @endif
        <x-ui.button type="submit">{{ __('pyramid::admin.ladder.settings.save') }}</x-ui.button>
    </form>
</x-ui.card>
