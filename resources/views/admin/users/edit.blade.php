@extends('layouts.admin')
@section('admin-title', 'Benutzer bearbeiten')
@section('admin-content')
<h1>Benutzer bearbeiten</h1>
<form method="POST" action="{{ route('admin.users.update', $user) }}">
    @method('PUT')
    @include('admin.users._form', ['privileges' => $privileges, 'user' => $user, 'profile' => $profile, 'granted' => $granted])
    <button type="submit" class="default-button">Speichern</button>
</form>
<hr>
<form method="POST" action="{{ route('admin.users.password', $user) }}">
    @csrf
    <label>Neues Passwort <input type="password" name="password"></label>
    <button type="submit" class="default-button">Passwort zurücksetzen</button>
</form>
<form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Benutzer löschen?')">
    @method('DELETE') @csrf
    <button type="submit" class="abmelden-button default-button">Benutzer löschen</button>
</form>
@endsection
