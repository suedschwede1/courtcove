@extends('layouts.admin')
@section('admin-title', 'Neuer Benutzer')
@section('admin-content')
<h1>Neuer Benutzer</h1>
<form method="POST" action="{{ route('admin.users.store') }}">
    @include('admin.users._form', ['privileges' => $privileges])
    <button type="submit" class="default-button">Anlegen</button>
</form>
@endsection
