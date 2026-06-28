@extends('layouts.admin')
@section('admin-title', __('booking.admin.squares.create_title'))
@section('admin-content')
<h1>{{ __('booking.admin.squares.create_title') }}</h1>
<form method="POST" action="{{ route('admin.squares.store') }}" class="admin-form">
    @include('admin.squares._form', ['form' => $form, 'square' => $square])
    <div class="admin-form__actions"><button type="submit" class="admin-btn-primary">{{ __('booking.admin.common.create') }}</button></div>
</form>
@endsection
