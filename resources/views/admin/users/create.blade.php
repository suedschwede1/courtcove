@extends('layouts.admin')
@section('admin-title', __('booking.admin.users.create_title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header">
        <h1>{{ __('booking.admin.users.create_title') }}</h1>
        <p>Neues Mitglied mit Konto, Profil und Rechten anlegen.</p>
    </div>
    <form method="POST" action="{{ route('admin.users.store') }}" class="ui-page">
        @include('admin.users._form', ['privileges' => $privileges])
        <div class="flex gap-3 items-center">
            <button type="submit" class="ui-btn ui-btn-primary">{{ __('booking.admin.common.create') }}</button>
        </div>
    </form>
</div>
@endsection
