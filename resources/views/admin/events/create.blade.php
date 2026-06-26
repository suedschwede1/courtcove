@extends('layouts.admin')
@section('admin-title', 'Neue Veranstaltung')
@section('admin-content')
<h1>Neue Veranstaltung</h1>
<form method="POST" action="{{ route('admin.events.store') }}">
    @include('admin.events._form', ['squares' => $squares])
    <button type="submit" class="default-button">Anlegen</button>
</form>
@endsection
