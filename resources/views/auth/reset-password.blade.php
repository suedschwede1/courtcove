@extends('layouts.base')

@section('title')
    {{ str_replace(':system', $bookingName, __('booking.password_reset.reset_title')) }}
@endsection

@section('body')
    <x-layout.auth-shell :title="__('booking.password_reset.reset_heading')" :intro="__('booking.password_reset.reset_intro')">
        <form method="POST" action="{{ route('password.update') }}" class="ds-auth__form">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            <x-ui.field :label="__('booking.password_reset.reset_password')" for="password">
                <x-ui.input type="password" value="" required autofocus autocomplete="new-password" />
            </x-ui.field>

            <x-ui.field :label="__('booking.password_reset.reset_password_confirm')" for="password_confirmation">
                <x-ui.input type="password" value="" required autocomplete="new-password" />
            </x-ui.field>

            @if($errors->has('email'))
                <x-ui.notice tone="problem">{{ $errors->first('email') }}</x-ui.notice>
            @endif

            <x-ui.button type="submit" variant="dark">{{ __('booking.password_reset.reset_submit') }}</x-ui.button>
        </form>
    </x-layout.auth-shell>
@endsection
