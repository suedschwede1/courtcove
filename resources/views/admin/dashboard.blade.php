@extends('layouts.admin')
@section('admin-title', __('booking.admin.dashboard.title'))
@section('admin-content')
    <h1>{{ __('booking.admin.overview') }}</h1>
    <hr class="admin-separator">
    <ul>
        @if(Route::has('admin.users.index'))@can('admin.user')<li><a href="{{ route('admin.users.index') }}">{{ __('booking.admin.dashboard.user_mgmt_link') }}</a></li>@endcan @endif
        @if(Route::has('admin.events.index'))@can('admin.event')<li><a href="{{ route('admin.events.index') }}">{{ __('booking.admin.dashboard.events_link') }}</a></li>@endcan @endif
        @if(Route::has('admin.bookings.index'))@can('admin.booking')<li><a href="{{ route('admin.bookings.index') }}">{{ __('booking.admin.dashboard.bookings_link') }}</a></li>@endcan @endif
        @if(Route::has('admin.config.edit'))@can('admin.config')<li><a href="{{ route('admin.config.edit') }}">{{ __('booking.admin.dashboard.config_link') }}</a></li>@endcan @endif
    </ul>
@endsection
