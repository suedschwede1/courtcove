@extends('layouts.admin')
@section('admin-title', __('booking.admin.users.edit_title'))
@section('admin-content')
<h1>{{ __('booking.admin.users.edit_title') }}</h1>
<form method="POST" action="{{ route('admin.users.update', $user) }}">
    @method('PUT')
    @include('admin.users._form', ['privileges' => $privileges, 'user' => $user, 'profile' => $profile, 'granted' => $granted])
    <button type="submit" class="default-button">{{ __('booking.admin.common.save') }}</button>
</form>
<hr>
<form method="POST" action="{{ route('admin.users.password', $user) }}">
    @csrf
    <label>{{ __('booking.admin.users.new_password') }} <input type="password" name="password"></label>
    <button type="submit" class="default-button">{{ __('booking.admin.users.reset_password') }}</button>
</form>
<form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('{{ __('booking.admin.users.confirm_delete') }}')">
    @method('DELETE') @csrf
    <button type="submit" class="abmelden-button default-button">{{ __('booking.admin.users.delete_user') }}</button>
</form>
@endsection
