@extends('layouts.admin')
@section('admin-title', __('booking.admin.users.create_title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header">
        <h1>{{ __('booking.admin.users.create_title') }}</h1>
        <p>Neues Mitglied mit Konto, Profil und Rechten anlegen.</p>
    </div>

    <form method="POST" action="{{ route('admin.users.store') }}" class="ui-form-shell">
        @include('admin.users._form', ['privileges' => $privileges])
        <div class="ui-card">
            <div class="ui-card-body ui-form-actions">
                <div class="ui-form-actions-group">
                    <button type="submit" class="ui-btn ui-btn-primary">{{ __('booking.admin.common.create') }}</button>
                    <a href="{{ route('admin.users.index') }}" class="ui-btn ui-btn-ghost">{{ __('booking.admin.common.abort') }}</a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
