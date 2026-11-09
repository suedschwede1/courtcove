@extends('layouts.base')

@section('title')
    {{ str_replace(':system', $bookingName, __('booking.password_reset.forgot_title')) }} – {{ config('booking.product_name') }}
@endsection

@section('body')
    <x-layout.auth-shell :title="__('booking.password_reset.forgot_heading')" :intro="__('booking.password_reset.forgot_intro')">
        @if(session('status'))
            <x-ui.notice tone="good">{{ session('status') }}</x-ui.notice>
        @else
            <form method="POST" action="{{ route('password.email') }}" class="ds-auth__form">
                @csrf
                <x-ui.field :label="__('booking.auth.email')" for="email">
                    <x-ui.input type="email" required autofocus autocomplete="email" :placeholder="__('booking.password_reset.forgot_email_placeholder')" />
                </x-ui.field>

                <x-ui.button type="submit" variant="dark">{{ __('booking.password_reset.forgot_submit') }}</x-ui.button>
            </form>
        @endif

        <x-slot:footer>
            <p><a class="ds-auth__link" href="{{ route('login') }}">{{ __('booking.password_reset.back_to_login') }}</a></p>
        </x-slot:footer>
    </x-layout.auth-shell>
@endsection
