@extends('layouts.admin')
@section('admin-title', __('booking.admin.squares.edit_title'))
@section('admin-content')
<h1>{{ __('booking.admin.squares.edit_title') }}</h1>
<form method="POST" action="{{ route('admin.squares.update', $square) }}" class="admin-form">
    @method('PUT')
    @include('admin.squares._form', ['form' => $form, 'square' => $square])
    <div class="admin-form__actions"><button type="submit" class="default-button">{{ __('booking.admin.common.save') }}</button></div>
</form>
@endsection
