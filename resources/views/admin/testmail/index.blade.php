@extends('layouts.admin')

@section('admin-title', 'Testmail')

@section('admin-content')
<div class="admin-card">
    <h2 class="admin-card__title">Testmail senden</h2>
    <p class="admin-card__desc">Sendet eine Test-E-Mail um zu prüfen ob der E-Mail-Versand korrekt konfiguriert ist.</p>

    <form method="POST" action="{{ route('admin.testmail.send') }}" class="admin-form">
        @csrf
        <div class="admin-form__row">
            <label class="admin-form__label" for="email">Empfänger-Adresse</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email', auth()->user()?->email) }}"
                class="admin-form__input @error('email') is-invalid @enderror"
                required
                autofocus
            >
            @error('email')
                <span class="admin-form__error">{{ $message }}</span>
            @enderror
        </div>

        <div class="admin-form__actions">
            <button type="submit" class="default-button">Testmail senden</button>
        </div>
    </form>
</div>
@endsection
