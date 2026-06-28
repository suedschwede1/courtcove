@extends('layouts.app')
@section('title', __('booking.account.my_account'))

@section('content')
<div class="panel" style="max-width:560px; margin:32px auto; padding:28px 32px;">
    <h1 style="font-size:20px; color:#C84B11; margin:0 0 24px 0;">{{ __('booking.account.my_account') }}</h1>

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

        <p><label>{{ __('booking.account.display_name') }}<br>
            <input type="text" name="alias" value="{{ old('alias', $user->alias) }}" required maxlength="128"></label></p>
        <p><label>{{ __('booking.account.email') }}<br>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="128"></label></p>
        <p><label>{{ __('booking.account.firstname') }}<br>
            <input type="text" name="firstname" value="{{ old('firstname', $profile['firstname']) }}" maxlength="128"></label></p>
        <p><label>{{ __('booking.account.lastname') }}<br>
            <input type="text" name="lastname" value="{{ old('lastname', $profile['lastname']) }}" maxlength="128"></label></p>
        <p><label>{{ __('booking.account.phone') }}<br>
            <input type="text" name="phone" value="{{ old('phone', $profile['phone']) }}" maxlength="128"></label></p>
        <p><label>{{ __('booking.account.street') }}<br>
            <input type="text" name="street" value="{{ old('street', $profile['street']) }}" maxlength="128"></label></p>
        <p><label>{{ __('booking.account.zip') }}<br>
            <input type="text" name="zip" value="{{ old('zip', $profile['zip']) }}" maxlength="128"></label></p>
        <p><label>{{ __('booking.account.city') }}<br>
            <input type="text" name="city" value="{{ old('city', $profile['city']) }}" maxlength="128"></label></p>
        <p><label>{{ __('booking.account.gender') }}<br>
            <select name="gender">
                @php($gender = old('gender', $profile['gender']))
                <option value="" @selected($gender === null || $gender === '')>–</option>
                <option value="male" @selected($gender === 'male')>{{ __('booking.account.male') }}</option>
                <option value="female" @selected($gender === 'female')>{{ __('booking.account.female') }}</option>
            </select></label></p>

        <button type="submit" class="default-button">{{ __('booking.admin.save') }}</button>
    </form>

    <hr style="margin:28px 0;">

    <h2 style="font-size:16px; margin:0 0 16px 0;">{{ __('booking.account.password_change') }}</h2>
    <form method="POST" action="{{ route('account.password') }}">
        @csrf
        @method('PUT')

        <p><label>{{ __('booking.account.current_password') }}<br>
            <input type="password" name="current_password"></label></p>
        <p><label>{{ __('booking.account.new_password') }}<br>
            <input type="password" name="password"></label></p>
        <p><label>{{ __('booking.account.new_password_confirmation') }}<br>
            <input type="password" name="password_confirmation"></label></p>

        <button type="submit" class="default-button">{{ __('booking.account.password_change') }}</button>
    </form>
</div>
@endsection
