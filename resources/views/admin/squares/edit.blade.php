@extends('layouts.admin')
@section('admin-title', 'Platz bearbeiten')
@section('admin-content')
<h1>Platz bearbeiten</h1>
<form method="POST" action="{{ route('admin.squares.update', $square) }}">
    @method('PUT')
    @include('admin.squares._form', ['form' => $form, 'square' => $square])
    <button type="submit" class="default-button">Speichern</button>
</form>
@endsection
