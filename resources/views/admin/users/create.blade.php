@extends('layouts.admin')
@section('admin-title', __('booking.admin.users.create_title'))
@section('admin-content')
<h1>{{ __('booking.admin.users.create_title') }}</h1>
<form method="POST" action="{{ route('admin.users.store') }}">
    @include('admin.users._form', ['privileges' => $privileges])
    <button type="submit" class="default-button">{{ __('booking.admin.common.create') }}</button>
</form>
@endsection
