@extends('layouts.app')
@section('title', 'Mein Konto')

@section('content')
<div class="panel" style="max-width:560px; margin:32px auto; padding:28px 32px;">
    <h1 style="font-size:20px; color:#C84B11; margin:0 0 24px 0;">Mein Konto</h1>

    @if(session('success'))
        <p style="color:#27764a; margin:0 0 16px 0;">{{ session('success') }}</p>
    @endif

    @if($errors->any())
        <ul style="color:#c0392b; margin:0 0 16px 0; padding-left:18px;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('account.update') }}">
        @csrf
        @method('PUT')

        <p><label>Anzeigename<br>
            <input type="text" name="alias" value="{{ old('alias', $user->alias) }}" required maxlength="128"></label></p>
        <p><label>E-Mail<br>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="128"></label></p>
        <p><label>Vorname<br>
            <input type="text" name="firstname" value="{{ old('firstname', $profile['firstname']) }}" maxlength="128"></label></p>
        <p><label>Nachname<br>
            <input type="text" name="lastname" value="{{ old('lastname', $profile['lastname']) }}" maxlength="128"></label></p>
        <p><label>Telefon<br>
            <input type="text" name="phone" value="{{ old('phone', $profile['phone']) }}" maxlength="128"></label></p>
        <p><label>Straße<br>
            <input type="text" name="street" value="{{ old('street', $profile['street']) }}" maxlength="128"></label></p>
        <p><label>PLZ<br>
            <input type="text" name="zip" value="{{ old('zip', $profile['zip']) }}" maxlength="128"></label></p>
        <p><label>Ort<br>
            <input type="text" name="city" value="{{ old('city', $profile['city']) }}" maxlength="128"></label></p>
        <p><label>Geschlecht<br>
            <select name="gender">
                @php($gender = old('gender', $profile['gender']))
                <option value="" @selected($gender === null || $gender === '')>–</option>
                <option value="male" @selected($gender === 'male')>männlich</option>
                <option value="female" @selected($gender === 'female')>weiblich</option>
            </select></label></p>

        <button type="submit" class="default-button">Speichern</button>
    </form>

    <hr style="margin:28px 0;">

    <h2 style="font-size:16px; margin:0 0 16px 0;">Passwort ändern</h2>
    <form method="POST" action="{{ route('account.password') }}">
        @csrf
        @method('PUT')

        <p><label>Aktuelles Passwort<br>
            <input type="password" name="current_password"></label></p>
        <p><label>Neues Passwort<br>
            <input type="password" name="password"></label></p>
        <p><label>Neues Passwort bestätigen<br>
            <input type="password" name="password_confirmation"></label></p>

        <button type="submit" class="default-button">Passwort ändern</button>
    </form>
</div>
@endsection
