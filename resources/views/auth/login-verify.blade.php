@extends('layouts.base')

@section('title')
    {{ str_replace(':system', $bookingName, __('booking.auth.verify.title')) }}
@endsection

@section('body')
    <x-layout.auth-shell :title="__('booking.auth.verify.heading')" :intro="($newDevice ?? false) ? __('booking.auth.verify.new_device_intro', ['days' => (int) config('booking.security.trusted_device_days')]) : __('booking.auth.verify.intro')">
        <form method="POST" action="{{ route('login.verify.submit') }}" class="ds-auth__form">
            @csrf
            <x-ui.field :label="__('booking.auth.verify.code_label')" for="code">
                <x-ui.input variant="score" value="" pattern="[0-9]*" maxlength="6" autofocus required />
            </x-ui.field>

            <x-ui.button type="submit" variant="dark">{{ __('booking.auth.verify.submit') }}</x-ui.button>
        </form>

        <form method="POST" action="{{ route('login.verify.resend') }}" class="ds-auth__form">
            @csrf
            <x-ui.button type="submit" variant="text">{{ __('booking.auth.verify.resend') }}</x-ui.button>
        </form>
    </x-layout.auth-shell>
@endsection
