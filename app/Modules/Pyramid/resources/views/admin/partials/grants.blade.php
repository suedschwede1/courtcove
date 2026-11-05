{{-- Per-ladder administration rights by user number. Needs: $ladder. --}}
@php
    use App\Modules\Pyramid\Support\PyramidLinks;
@endphp
<x-ui.card :title="__('pyramid::admin.ladder.grants.title')">
    <div class="flex flex-col gap-4">
        <form method="POST" action="{{ PyramidLinks::adminLadderAction('grant', (int) $ladder->id) }}" class="flex flex-col gap-3">
            @csrf
            <x-ui.field for="pyramid-grant-uid" :label="__('pyramid::admin.ladder.grants.uid')">
                <x-ui.input type="number" name="uid" id="pyramid-grant-uid" :value="''" inputmode="numeric" min="1" required />
            </x-ui.field>
            <x-ui.button type="submit">{{ __('pyramid::admin.ladder.grants.grant') }}</x-ui.button>
        </form>
        <form method="POST" action="{{ PyramidLinks::adminLadderAction('revoke', (int) $ladder->id) }}" class="flex flex-col gap-3">
            @csrf
            <x-ui.field for="pyramid-revoke-uid" :label="__('pyramid::admin.ladder.grants.uid')">
                <x-ui.input type="number" name="uid" id="pyramid-revoke-uid" :value="''" inputmode="numeric" min="1" required />
            </x-ui.field>
            <x-ui.button type="submit" variant="danger">{{ __('pyramid::admin.ladder.grants.revoke') }}</x-ui.button>
        </form>
    </div>
</x-ui.card>
