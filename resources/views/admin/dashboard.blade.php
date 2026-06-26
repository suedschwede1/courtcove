@extends('layouts.admin')
@section('admin-title', 'Übersicht')
@section('admin-content')
    <h1>Administration</h1>
    <ul>
        @if(Route::has('admin.users.index'))@can('admin.user')<li><a href="{{ route('admin.users.index') }}">Benutzerverwaltung</a></li>@endcan @endif
        @if(Route::has('admin.events.index'))@can('admin.event')<li><a href="{{ route('admin.events.index') }}">Veranstaltungen</a></li>@endcan @endif
        @if(Route::has('admin.bookings.index'))@can('admin.booking')<li><a href="{{ route('admin.bookings.index') }}">Buchungen</a></li>@endcan @endif
        @if(Route::has('admin.config.edit'))@can('admin.config')<li><a href="{{ route('admin.config.edit') }}">Konfiguration</a></li>@endcan @endif
    </ul>
@endsection
