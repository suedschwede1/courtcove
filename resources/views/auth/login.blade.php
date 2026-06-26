@extends('layouts.app')
@section('title', 'Anmelden – Example Tennis Club Sampletown')

@section('content')
<div class="standalone-login">
    <section class="centered-panel login-page-panel">
        <div class="login-page-copy">
            <p class="eyebrow">Mitgliedsbereich</p>
            <h1>Anmelden</h1>
            <p>
                Melden Sie sich mit Ihren Zugangsdaten an, um freie Plätze direkt aus dem
                Belegungsplan zu buchen oder bestehende Reservierungen zu stornieren.
            </p>
        </div>

        @include('auth._form', ['redirectTo' => $redirectTo ?? route('calendar.index')])
    </section>
</div>
@endsection
