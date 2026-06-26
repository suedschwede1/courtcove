@extends('layouts.admin')
@section('admin-title', 'Konfiguration')
@section('admin-content')
<h1>Konfiguration</h1>
<form method="POST" action="{{ route('admin.config.update') }}">
    @method('PUT')
    @csrf
    <label>Vollständiger Vereinsname
        <input type="text" name="client_name_full" value="{{ $values['client_name_full'] }}">
    </label>
    <label>Kontakt-E-Mail
        <input type="email" name="contact_email" value="{{ $values['contact_email'] }}">
    </label>
    <label>Kalendertage
        <input type="number" name="calendar_days" min="1" max="31" value="{{ $values['calendar_days'] }}">
    </label>
    <label>Registrierung
        <select name="registration">
            <option value="0" @selected((string) $values['registration'] === '0')>Nein</option>
            <option value="1" @selected((string) $values['registration'] === '1')>Ja</option>
        </select>
    </label>
    <label>Wartungsmodus
        <select name="maintenance">
            <option value="0" @selected((string) $values['maintenance'] === '0')>Aus</option>
            <option value="1" @selected((string) $values['maintenance'] === '1')>An</option>
        </select>
    </label>
    <button type="submit" class="default-button">Speichern</button>
</form>
@endsection
