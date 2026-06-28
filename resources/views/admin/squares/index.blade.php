@extends('layouts.admin')
@section('admin-title', 'Plätze')
@section('admin-content')
    <h1>Plätze</h1>
    <a href="{{ route('admin.squares.create') }}" class="default-button">Neuer Platz</a>
    <table class="booking-grid">
        <thead><tr><th>Name</th><th>Anzeigename</th><th>Status</th><th>Zeit</th><th>Zeitblock</th><th></th></tr></thead>
        <tbody>
        @foreach($squares as $square)
            <tr>
                <td>{{ $square->name }}</td>
                <td>{{ $square->display_name }}</td>
                <td>{{ $square->status->value }}</td>
                <td>{{ substr((string) $square->time_start, 0, 5) }}–{{ substr((string) $square->time_end, 0, 5) }} Uhr</td>
                <td>{{ (int) round($square->time_block / 60) }} Min</td>
                <td>
                    <a href="{{ route('admin.squares.edit', $square) }}">Bearbeiten</a>
                    <form method="POST" action="{{ route('admin.squares.destroy', $square) }}" onsubmit="return confirm('Platz löschen?')" style="display:inline">
                        @method('DELETE') @csrf
                        <button type="submit" class="abmelden-button default-button">Löschen</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
