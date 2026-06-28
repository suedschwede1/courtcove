@extends('layouts.app')
@section('title', __('booking.auth.title', ['system' => $bookingName]))

@section('content')
<div class="standalone-login">
    <section class="centered-panel login-page-panel">
        <div class="login-page-copy">
            <p class="eyebrow">{{ __('booking.auth.eyebrow') }}</p>
            <h1>{{ __('booking.auth.heading') }}</h1>
            <p>{{ __('booking.auth.intro') }}</p>
        </div>

        @include('auth._form', ['redirectTo' => $redirectTo ?? route('calendar.index')])
    </section>
</div>
@endsection
