@extends('layouts.admin')
@section('admin-title', 'Veranstaltung bearbeiten')
@section('admin-content')
<h1>Veranstaltung bearbeiten</h1>
<form method="POST" action="{{ route('admin.events.update', $event) }}">
    @method('PUT')
    @include('admin.events._form', ['squares' => $squares, 'event' => $event, 'name' => $name])
    <button type="submit" class="default-button">Speichern</button>
</form>
@endsection
