@extends('layouts.admin')
@section('admin-title', 'Benutzer')
@section('admin-content')
    <h1>Benutzer</h1>
    <a href="{{ route('admin.users.create') }}" class="default-button">Neuer Benutzer</a>
    <table class="booking-grid"><thead><tr><th>Name</th><th>E-Mail</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @foreach($users as $u)
        <tr><td>{{ $u->alias }}</td><td>{{ $u->email }}</td><td>{{ $u->status }}</td>
            <td><a href="{{ route('admin.users.edit', $u) }}">Bearbeiten</a></td></tr>
    @endforeach
    </tbody></table>
@endsection
