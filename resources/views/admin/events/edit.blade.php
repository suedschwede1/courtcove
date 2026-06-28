@extends('layouts.admin')
@section('admin-title', __('booking.admin.events.edit_title'))
@section('admin-content')
@unless(request('popup'))
<h1>{{ __('booking.admin.events.edit_title') }}</h1>
@endunless
<form method="POST" action="{{ route('admin.events.update', $event) }}" class="admin-form admin-event-form">
    @csrf
    @method('PUT')
    @if(request('popup'))
    <input type="hidden" name="popup" value="1">
    <input type="hidden" name="redirect_to" value="{{ route('calendar.index', ['date' => $date_start ?: now()->format('Y-m-d')]) }}">
    @endif
    @include('admin.events._form')
    <div class="admin-form__actions"><button type="submit" class="admin-btn-primary">{{ __('booking.admin.common.save') }}</button></div>
</form>
@endsection
