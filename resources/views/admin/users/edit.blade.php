@extends('layouts.admin')
@section('admin-title', __('booking.admin.users.edit_title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header">
        <h1>{{ __('booking.admin.users.edit_title') }}</h1>
        <p>{{ __('booking.admin.users.intro_edit') }}</p>
    </div>

    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="ui-form-shell">
        @method('PUT')
        @include('admin.users._form', ['privileges' => $privileges, 'user' => $user, 'profile' => $profile, 'granted' => $granted])
        <div class="ui-card">
            <div class="ui-card-body ui-form-actions">
                <div class="ui-form-actions-group">
                    <button type="submit" class="ui-btn ui-btn-primary">{{ __('booking.admin.common.save') }}</button>
                    <a href="{{ route('admin.users.index') }}" class="ui-btn ui-btn-ghost">{{ __('booking.admin.common.abort') }}</a>
                </div>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.users.password', $user) }}" class="ui-card">
        @csrf
        <div class="ui-card-header"><h2>{{ __('booking.admin.users.reset_password') }}</h2></div>
        <div class="ui-card-body ui-form-divider">
            <p class="ui-section-label !mb-0">{{ __('booking.admin.users.security') }}</p>
            <div class="ui-row">
                <div class="ui-field min-w-[18rem]">
                    <label class="ui-label" for="uf-new-pw">{{ __('booking.admin.users.new_password') }}</label>
                    <input id="uf-new-pw" type="password" name="password" class="ui-input">
                </div>
                <button type="submit" class="ui-btn ui-btn-outline">{{ __('booking.admin.users.reset_password') }}</button>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="ui-card" onsubmit="return confirm({{ Js::from(__('booking.admin.users.confirm_delete')) }})">
        @method('DELETE')
        @csrf
        <div class="ui-card-header"><h2>{{ __('booking.admin.users.delete_user') }}</h2></div>
        <div class="ui-card-body ui-form-actions">
            <p class="ui-note">{{ __('booking.admin.users.delete_note') }}</p>
            <div class="ui-form-actions-group">
                <button type="submit" class="ui-btn ui-btn-danger">{{ __('booking.admin.users.delete_user') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection
