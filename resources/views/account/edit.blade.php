@extends('layouts.app')
@section('title', __('booking.account.my_account'))

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8 ui-page">
    <div class="ui-page-header">
        <h1>{{ __('booking.account.my_account') }}</h1>
        <p>Profil- und Zugangsdaten im Mitgliedsbereich verwalten.</p>
    </div>

    @if(session('success'))
        <div class="ui-flash ui-flash-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="ui-flash ui-flash-error">{{ session('error') }}</div>
    @endif

    <div class="ui-card">
        <div class="ui-card-header"><h2>Profil</h2></div>
        <form method="POST" action="{{ route('account.update') }}">
            @csrf
            @method('PUT')
            <div class="ui-card-body ui-stack">
                <div class="ui-field">
                    <label class="ui-label">{{ __('booking.account.display_name') }}</label>
                    <input type="text" name="alias" value="{{ old('alias', $user->alias) }}" required maxlength="128" class="ui-input">
                    @error('alias')<p class="ui-error">{{ $message }}</p>@enderror
                </div>
                <div class="ui-grid-2">
                    <div class="ui-field"><label class="ui-label">{{ __('booking.account.firstname') }}</label><input type="text" name="firstname" value="{{ old('firstname', $profile['firstname']) }}" maxlength="128" class="ui-input">@error('firstname')<p class="ui-error">{{ $message }}</p>@enderror</div>
                    <div class="ui-field"><label class="ui-label">{{ __('booking.account.lastname') }}</label><input type="text" name="lastname" value="{{ old('lastname', $profile['lastname']) }}" maxlength="128" class="ui-input">@error('lastname')<p class="ui-error">{{ $message }}</p>@enderror</div>
                    <div class="ui-field"><label class="ui-label">{{ __('booking.account.email') }}</label><input type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="128" class="ui-input">@error('email')<p class="ui-error">{{ $message }}</p>@enderror</div>
                    <div class="ui-field"><label class="ui-label">{{ __('booking.account.phone') }}</label><input type="text" name="phone" value="{{ old('phone', $profile['phone']) }}" maxlength="128" class="ui-input">@error('phone')<p class="ui-error">{{ $message }}</p>@enderror</div>
                    <div class="ui-field"><label class="ui-label">{{ __('booking.account.street') }}</label><input type="text" name="street" value="{{ old('street', $profile['street']) }}" maxlength="128" class="ui-input">@error('street')<p class="ui-error">{{ $message }}</p>@enderror</div>
                    <div class="ui-field"><label class="ui-label">{{ __('booking.account.zip') }}</label><input type="text" name="zip" value="{{ old('zip', $profile['zip']) }}" maxlength="10" class="ui-input">@error('zip')<p class="ui-error">{{ $message }}</p>@enderror</div>
                    <div class="ui-field"><label class="ui-label">{{ __('booking.account.city') }}</label><input type="text" name="city" value="{{ old('city', $profile['city']) }}" maxlength="128" class="ui-input">@error('city')<p class="ui-error">{{ $message }}</p>@enderror</div>
                    <div class="ui-field">
                        <label class="ui-label">{{ __('booking.account.gender') }}</label>
                        <select name="gender" class="ui-select">
                            @php($gender = old('gender', $profile['gender']))
                            <option value="" @selected($gender === null || $gender === '')>–</option>
                            <option value="male" @selected($gender === 'male')>{{ __('booking.account.male') }}</option>
                            <option value="female" @selected($gender === 'female')>{{ __('booking.account.female') }}</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="ui-card-body pt-0 flex justify-end"><button type="submit" class="ui-btn ui-btn-primary">{{ __('booking.admin.save') }}</button></div>
        </form>
    </div>

    <div class="ui-card">
        <div class="ui-card-header"><h2>{{ __('booking.account.password_change') }}</h2></div>
        <form method="POST" action="{{ route('account.password') }}">
            @csrf
            @method('PUT')
            <div class="ui-card-body ui-grid-3">
                <div class="ui-field"><label class="ui-label">{{ __('booking.account.current_password') }}</label><input type="password" name="current_password" required class="ui-input">@error('current_password')<p class="ui-error">{{ $message }}</p>@enderror</div>
                <div class="ui-field"><label class="ui-label">{{ __('booking.account.new_password') }}</label><input type="password" name="password" required class="ui-input">@error('password')<p class="ui-error">{{ $message }}</p>@enderror</div>
                <div class="ui-field"><label class="ui-label">{{ __('booking.account.new_password_confirmation') }}</label><input type="password" name="password_confirmation" required class="ui-input">@error('password_confirmation')<p class="ui-error">{{ $message }}</p>@enderror</div>
            </div>
            <div class="ui-card-body pt-0 flex justify-end"><button type="submit" class="ui-btn ui-btn-primary">{{ __('booking.account.password_change') }}</button></div>
        </form>
    </div>
</div>
@endsection
