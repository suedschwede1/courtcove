@extends('layouts.admin')
@section('admin-title', __('booking.admin.users.edit_title'))
@section('admin-content')
<h1>{{ __('booking.admin.users.edit_title') }}</h1>
<form method="POST" action="{{ route('admin.users.update', $user) }}" class="admin-form">
    @method('PUT')
    @include('admin.users._form', ['privileges' => $privileges, 'user' => $user, 'profile' => $profile, 'granted' => $granted])
    <div class="admin-form__actions">
        <button type="submit" class="admin-btn-primary">{{ __('booking.admin.common.save') }}</button>
    </div>
</form>
<form method="POST" action="{{ route('admin.users.password', $user) }}" class="admin-form">
    @csrf
    <div class="admin-form__section">
        <div class="admin-form__section-title">{{ __('booking.admin.users.reset_password') }}</div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="uf-new-pw">{{ __('booking.admin.users.new_password') }}</label>
            <div class="admin-form__field"><input id="uf-new-pw" type="password" name="password"></div>
        </div>
    </div>
    <div class="admin-form__actions">
        <button type="submit" class="default-button">{{ __('booking.admin.users.reset_password') }}</button>
    </div>
</form>
<form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="admin-form" onsubmit="return confirm('{{ __('booking.admin.users.confirm_delete') }}')">
    @method('DELETE') @csrf
    <div class="admin-form__actions">
        <button type="submit" class="abmelden-button default-button">{{ __('booking.admin.users.delete_user') }}</button>
    </div>
</form>
@endsection
