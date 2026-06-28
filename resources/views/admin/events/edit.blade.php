@extends('layouts.admin')
@section('admin-title', __('booking.admin.events.edit_title'))
@section('admin-content')
<h1>{{ __('booking.admin.events.edit_title') }}</h1>
<form method="POST" action="{{ route('admin.events.update', $event) }}">
    @method('PUT')
    @include('admin.events._form', ['squares' => $squares, 'event' => $event, 'name' => $name])
    <button type="submit" class="default-button">{{ __('booking.admin.common.save') }}</button>
</form>
@endsection
