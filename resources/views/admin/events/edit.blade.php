@extends('layouts.admin')
@section('admin-title', __('booking.admin.events.edit_title'))
@section('admin-content')
<h1>{{ __('booking.admin.events.edit_title') }}</h1>
<form method="POST" action="{{ route('admin.events.update', $event) }}" class="admin-form">
    @csrf
    @method('PUT')
    @include('admin.events._form', ['squares' => $squares, 'event' => $event, 'name' => $name])
    <div class="admin-form__actions"><button type="submit" class="admin-btn-primary">{{ __('booking.admin.common.save') }}</button></div>
</form>
@endsection
