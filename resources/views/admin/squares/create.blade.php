@extends('layouts.admin')
@section('admin-title', __('booking.admin.squares.create_title'))
@section('admin-content')
<h1>{{ __('booking.admin.squares.create_title') }}</h1>
<form method="POST" action="{{ route('admin.squares.store') }}">
    @include('admin.squares._form', ['form' => $form, 'square' => $square])
    <button type="submit" class="default-button">{{ __('booking.admin.common.create') }}</button>
</form>
@endsection
