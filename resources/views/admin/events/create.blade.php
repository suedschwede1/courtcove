@extends('layouts.admin')
@section('admin-title', __('booking.admin.events.create_title'))
@section('admin-content')
<h1>{{ __('booking.admin.events.create_title') }}</h1>
<form method="POST" action="{{ route('admin.events.store') }}" class="admin-form">
    @csrf
    @include('admin.events._form', ['squares' => $squares])
    <div class="admin-form__actions"><button type="submit" class="admin-btn-primary">{{ __('booking.admin.common.create') }}</button></div>
</form>
@endsection
