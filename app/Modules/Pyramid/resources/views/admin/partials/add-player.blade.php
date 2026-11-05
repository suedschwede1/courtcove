{{-- Add a participant (lands on the waiting list). Needs: $ladder. --}}
@php
    use App\Modules\Pyramid\Support\PyramidLinks;
@endphp
<x-ui.card :title="__('pyramid::admin.ladder.add.title')" id="pyramid-admin-add">
    <div
        class="flex flex-col gap-4"
        x-data="{ uid: @js((string) old('uid', '')), label: @js((string) old('label', '')), email: @js((string) old('email', '')) }"
    >
        @include('pyramid::admin.partials.member-picker')

        <form method="POST" action="{{ PyramidLinks::adminLadderAction('add-player', (int) $ladder->id) }}" class="flex flex-col gap-3">
            @csrf
            <x-ui.field for="pyramid-add-label" :label="__('pyramid::admin.ladder.add.label')">
                <x-ui.input name="label" id="pyramid-add-label" required x-model="label" />
            </x-ui.field>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.field for="pyramid-add-uid" :label="__('pyramid::admin.ladder.add.uid')">
                    <x-ui.input name="uid" id="pyramid-add-uid" inputmode="numeric" autocomplete="off" x-model="uid" />
                </x-ui.field>
                <x-ui.field for="pyramid-add-itn" :label="__('pyramid::admin.ladder.add.itn')">
                    <x-ui.input name="itn" id="pyramid-add-itn" inputmode="decimal" />
                </x-ui.field>
            </div>
            <x-ui.field for="pyramid-add-email" :label="__('pyramid::admin.ladder.add.email')">
                <x-ui.input type="email" name="email" id="pyramid-add-email" autocomplete="off" x-model="email" />
            </x-ui.field>
            <x-ui.option-group :legend="__('pyramid::admin.ladder.add.gender')" :columns="3">
                <x-ui.option-tile name="gender" value="" :label="__('pyramid::admin.ladder.gender.none')" :checked="! in_array(old('gender'), ['m', 'f'], true)" />
                <x-ui.option-tile name="gender" value="m" :label="__('pyramid::admin.ladder.gender.m')" :checked="old('gender') === 'm'" />
                <x-ui.option-tile name="gender" value="f" :label="__('pyramid::admin.ladder.gender.f')" :checked="old('gender') === 'f'" />
            </x-ui.option-group>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.field for="pyramid-add-age" :label="__('pyramid::admin.ladder.add.age')">
                    <x-ui.input type="number" name="age" id="pyramid-add-age" min="0" inputmode="numeric" />
                </x-ui.field>
                <x-ui.field for="pyramid-add-phone" :label="__('pyramid::admin.ladder.add.phone')">
                    <x-ui.input type="tel" name="phone" id="pyramid-add-phone" />
                </x-ui.field>
            </div>
            <x-ui.button type="submit" variant="dark">{{ __('pyramid::admin.ladder.add.submit') }}</x-ui.button>
        </form>
    </div>
</x-ui.card>
