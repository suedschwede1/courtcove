@extends('layouts.admin')
@section('admin-title', __('booking.admin.events.create_title'))
@section('admin-content')
<h1>{{ __('booking.admin.events.create_title') }}</h1>
<form method="POST" action="{{ route('admin.events.store') }}">
    @include('admin.events._form', ['squares' => $squares])
    <button type="submit" class="default-button">{{ __('booking.admin.common.create') }}</button>
</form>
@endsection
