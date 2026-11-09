@extends('layouts.base')

@section('title')
    {{ str_replace(':system', $bookingName, __('booking.register.title')) }} – {{ config('booking.product_name') }}
@endsection

@section('body')
@php
    $registrationHeading = str_replace(':system', $bookingName, $registrationContent['heading']);
    $registrationWelcome = str_replace(':system', $bookingName, $registrationContent['welcome']);
    $registrationIntro = str_replace(':system', $bookingName, $registrationContent['intro']);
    $registrationEmailHelp = str_replace(':system', $bookingName, $registrationContent['email_help']);
    $privacyUrl = trim((string) \App\Models\Option::getValue('client.website.privacy', '#'));
    $privacyLink = '<a href="'.e($privacyUrl !== '' ? $privacyUrl : '#').'" class="ds-auth__link" target="_blank" rel="noopener noreferrer">'.e(__('booking.register.privacy_link')).'</a>';
    $registrationPrivacy = str_replace([':system', ':privacy_policy'], [$bookingName, $privacyLink], $registrationContent['privacy']);
@endphp

<x-layout.auth-shell :title="$registrationHeading">
    <p class="ds-auth__lead">{{ $registrationWelcome }}</p>
    <p class="ds-auth__intro">{{ $registrationIntro }}</p>

    <form method="POST" action="{{ route('register') }}" class="ds-auth__form">
        @csrf

        <fieldset class="ds-auth__section">
            <legend class="ds-auth__section-title">{{ __('booking.register.account_section') }}</legend>

            <x-ui.field :label="__('booking.register.email')" for="email" :help="$registrationEmailHelp">
                <x-ui.input type="email" autocomplete="email" />
            </x-ui.field>

            <x-ui.field :label="__('booking.register.email_confirm')" for="email_confirm" :help="__('booking.register.email_confirm_help')">
                <x-ui.input type="email" autocomplete="off" />
            </x-ui.field>

            <x-ui.field :label="__('booking.register.password')" for="password" :help="__('booking.register.password_help')">
                <x-ui.input type="password" value="" autocomplete="new-password" />
            </x-ui.field>

            <x-ui.field :label="__('booking.register.password_confirm')" for="password_confirm" :help="__('booking.register.password_confirm_help')">
                <x-ui.input type="password" value="" autocomplete="new-password" />
            </x-ui.field>
        </fieldset>

        <fieldset class="ds-auth__section">
            <legend class="ds-auth__section-title">{{ __('booking.register.profile_section') }}</legend>

            <x-ui.field :label="__('booking.register.gender')" for="gender">
                <x-ui.select
                    :options="['m' => __('booking.register.gender_male'), 'f' => __('booking.register.gender_female'), 'd' => __('booking.register.gender_diverse')]"
                    :placeholder="__('booking.register.gender_placeholder')" />
            </x-ui.field>

            <div class="ds-auth__pair">
                <x-ui.field :label="__('booking.register.firstname_placeholder').' *'" for="firstname">
                    <x-ui.input autocomplete="given-name" />
                </x-ui.field>
                <x-ui.field :label="__('booking.register.lastname_placeholder').' *'" for="lastname">
                    <x-ui.input autocomplete="family-name" />
                </x-ui.field>
            </div>

            <x-ui.field :label="__('booking.register.street')" for="street">
                <x-ui.input autocomplete="street-address" />
            </x-ui.field>

            <div class="ds-auth__pair ds-auth__pair--zip">
                <x-ui.field :label="__('booking.register.zip_placeholder')" for="zip">
                    <x-ui.input autocomplete="postal-code" />
                </x-ui.field>
                <x-ui.field :label="__('booking.register.city_placeholder')" for="city">
                    <x-ui.input autocomplete="address-level2" />
                </x-ui.field>
            </div>

            <x-ui.field :label="__('booking.register.phone').' *'" for="phone" :help="__('booking.register.phone_help')">
                <x-ui.input type="tel" autocomplete="tel" />
            </x-ui.field>
        </fieldset>

        <div class="ds-auth__form">
            <label class="ds-auth__check" for="privacy">
                <input type="checkbox" id="privacy" name="privacy" value="1" @if($errors->has('privacy')) aria-invalid="true" aria-describedby="privacy-error" @endif>
                <span>{!! $registrationPrivacy !!}</span>
            </label>
            @error('privacy')
                <p class="ds-field__error" id="privacy-error">{{ $message }}</p>
            @enderror

            <x-ui.button type="submit" variant="dark">{{ __('booking.register.submit') }}</x-ui.button>
        </div>
    </form>

    <x-slot:footer>
        <p>{{ __('booking.register.already_registered') }} <a class="ds-auth__link" href="{{ route('login') }}">{{ __('booking.nav.login') }}</a></p>
    </x-slot:footer>
</x-layout.auth-shell>
@endsection
