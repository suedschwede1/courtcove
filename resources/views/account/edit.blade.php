@extends('layouts.app')
@section('title', __('booking.account.my_account'))

@section('content')
<div class="mx-auto flex w-full max-w-350 flex-col gap-5 px-4 py-6 md:px-6 md:py-8">
    <x-ui.page-header
        :eyebrow="__('booking.account.eyebrow')"
        :title="__('booking.account.my_account')"
        :intro="__('booking.account.intro')" />

    @if(session('success'))
        <x-ui.notice tone="good">{{ session('success') }}</x-ui.notice>
    @endif
    @if(session('error'))
        <x-ui.notice tone="problem">{{ session('error') }}</x-ui.notice>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="flex flex-col gap-5 lg:col-span-2">
        <x-ui.card :title="__('booking.account.profile')">
            <form method="POST" action="{{ route('account.update') }}" class="flex flex-col gap-4">
                @csrf
                @method('PUT')

                <x-ui.field :label="__('booking.account.display_name')" for="alias">
                    <x-ui.input name="alias" id="alias" required maxlength="128" :value="old('alias', $user->alias)" />
                </x-ui.field>

                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.field :label="__('booking.account.firstname')" for="firstname">
                        <x-ui.input name="firstname" id="firstname" maxlength="128" :value="old('firstname', $profile['firstname'])" />
                    </x-ui.field>
                    <x-ui.field :label="__('booking.account.lastname')" for="lastname">
                        <x-ui.input name="lastname" id="lastname" maxlength="128" :value="old('lastname', $profile['lastname'])" />
                    </x-ui.field>
                    <x-ui.field :label="__('booking.account.email')" for="email">
                        <x-ui.input name="email" id="email" type="email" maxlength="128" :value="old('email', $user->email)" />
                    </x-ui.field>
                    <x-ui.field :label="__('booking.account.phone')" for="phone">
                        <x-ui.input name="phone" id="phone" maxlength="128" :value="old('phone', $profile['phone'])" />
                    </x-ui.field>
                    <x-ui.field :label="__('booking.account.street')" for="street">
                        <x-ui.input name="street" id="street" maxlength="128" :value="old('street', $profile['street'])" />
                    </x-ui.field>
                    <x-ui.field :label="__('booking.account.zip')" for="zip">
                        <x-ui.input name="zip" id="zip" maxlength="10" :value="old('zip', $profile['zip'])" />
                    </x-ui.field>
                    <x-ui.field :label="__('booking.account.city')" for="city">
                        <x-ui.input name="city" id="city" maxlength="128" :value="old('city', $profile['city'])" />
                    </x-ui.field>
                    <x-ui.field :label="__('booking.account.gender')" for="gender">
                        <x-ui.select name="gender" id="gender"
                            :options="['' => __('booking.account.empty_option'), 'male' => __('booking.account.male'), 'female' => __('booking.account.female')]"
                            :selected="(string) old('gender', $profile['gender'] ?? '')" />
                    </x-ui.field>
                </div>

                <div class="ds-admin-choice">
                    {{-- After a validation error of this form (alias is always sent) an unticked box stays unticked. --}}
                    <x-ui.option-group :legend="__('booking.account.player_exchange')" :columns="2" class="ds-admin-flags ds-admin-flags--single" aria-describedby="player-exchange-help">
                        <x-ui.option-tile type="checkbox" name="player_exchange" value="1" :label="__('booking.account.player_exchange_label')" :checked="old('alias') !== null ? (bool) old('player_exchange') : $playerExchange" />
                    </x-ui.option-group>
                    <p class="ds-field__help" id="player-exchange-help">{{ __('booking.account.player_exchange_help') }}</p>
                </div>

                <div class="flex justify-end">
                    <x-ui.button type="submit" variant="dark" :block="false">{{ __('booking.admin.save') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        @include('account.partials.whatsapp-card', ['whatsapp' => $whatsapp])
        </div>

        <x-ui.card :title="__('booking.account.password_change')">
            <form method="POST" action="{{ route('account.password') }}" class="flex flex-col gap-4">
                @csrf
                @method('PUT')

                <x-ui.field :label="__('booking.account.current_password')" for="current_password">
                    <x-ui.input type="password" name="current_password" id="current_password" value="" required autocomplete="current-password" />
                </x-ui.field>
                <x-ui.field :label="__('booking.account.new_password')" for="password">
                    <x-ui.input type="password" name="password" id="password" value="" required autocomplete="new-password" />
                </x-ui.field>
                <x-ui.field :label="__('booking.account.new_password_confirmation')" for="password_confirmation">
                    <x-ui.input type="password" name="password_confirmation" id="password_confirmation" value="" required autocomplete="new-password" />
                </x-ui.field>

                <div class="flex justify-end">
                    <x-ui.button type="submit" variant="dark" :block="false">{{ __('booking.account.password_change') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</div>
@endsection
