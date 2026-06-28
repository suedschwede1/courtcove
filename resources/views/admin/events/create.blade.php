@extends('layouts.admin')
@section('admin-title', __('booking.admin.events.create_title'))
@section('admin-content')
<h1>{{ __('booking.admin.events.create_title') }}</h1>

@php
    $bookingUrl = route('admin.bookings.create', array_filter([
        'sid'        => request('sid'),
        'date'       => request('date_start'),
        'time_start' => request('time_start'),
        'time_end'   => request('time_end'),
    ]));
@endphp
<div class="admin-type-switcher">
    <a href="{{ $bookingUrl }}" class="admin-type-switcher__tab">{{ __('booking.admin.bookings.type_booking') }}</a>
    <span class="admin-type-switcher__tab admin-type-switcher__tab--active">{{ __('booking.admin.bookings.type_event') }}</span>
</div>

<form method="POST" action="{{ route('admin.events.store') }}" class="admin-form">
    @csrf
    @include('admin.events._form', ['squares' => $squares])
    <div class="admin-form__actions"><button type="submit" class="admin-btn-primary">{{ __('booking.admin.common.create') }}</button></div>
</form>
@endsection
