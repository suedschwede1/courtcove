@extends('layouts.admin')
@section('admin-title', 'Neuer Platz')
@section('admin-content')
<h1>Neuer Platz</h1>
<form method="POST" action="{{ route('admin.squares.store') }}">
    @include('admin.squares._form', ['form' => $form, 'square' => $square])
    <button type="submit" class="default-button">Anlegen</button>
</form>
@endsection
