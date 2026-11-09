@extends('layouts.base')

@section('title')
    {{ str_replace(':system', $bookingName, __('booking.auth.title')) }} – {{ config('booking.product_name') }}
@endsection

@section('body')
    <x-layout.auth-shell :title="__('booking.auth.heading')" :intro="__('booking.auth.intro')" :intro-desktop-only="true">
        @if(session('registered'))
            <x-ui.notice tone="good">{{ str_replace(':system', $bookingName, $registrationContent['success']) }}</x-ui.notice>
        @endif

        <form method="POST" action="{{ route('login') }}" class="ds-auth__form">
            @csrf
            @if(request('redirect_to'))
                <input type="hidden" name="redirect_to" value="{{ request('redirect_to') }}">
            @endif

            <x-ui.field :label="__('booking.auth.email')" for="email">
                <x-ui.input type="email" required autofocus autocomplete="email" :placeholder="__('booking.auth.email_placeholder')" />
            </x-ui.field>

            <div class="ds-auth__field-wrap">
                <x-ui.field :label="__('booking.auth.password')" for="password">
                    <x-ui.input type="password" value="" required autocomplete="current-password" />
                </x-ui.field>
                <a class="ds-auth__field-link" href="{{ route('password.forgot') }}">{{ __('booking.auth.forgot_password') }}</a>
            </div>

            <label class="ds-auth__check" for="remember">
                <input type="checkbox" id="remember" name="remember">
                <span>{{ __('booking.auth.remember') }}</span>
            </label>

            @if($errors->any() && ! $errors->has('alias') && ! $errors->has('password') && ! $errors->has('email'))
                <x-ui.notice tone="problem">{{ $errors->first() }}</x-ui.notice>
            @endif

            <x-ui.button type="submit" variant="dark">{{ __('booking.auth.submit') }}</x-ui.button>
        </form>

        <x-slot:footer>
            <p>{{ __('booking.auth.no_account') }} <a class="ds-auth__link" href="{{ route('register') }}">{{ __('booking.auth.register_now') }}</a> – {{ __('booking.auth.register_note') }}</p>
        </x-slot:footer>
    </x-layout.auth-shell>
@endsection
