@extends('layouts.admin')
@section('admin-title', 'Veranstaltungen')
@section('admin-content')
    <h1>Veranstaltungen</h1>
    <a href="{{ route('admin.events.create') }}" class="default-button">Neue Veranstaltung</a>
    <table class="booking-grid"><thead><tr><th>Name</th><th>Platz</th><th>Von</th><th>Bis</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @foreach($events as $event)
        <tr>
            <td>{{ $event->meta->firstWhere('key', 'name')?->value ?? '—' }}</td>
            <td>{{ $event->square?->display_name ?? 'Alle Plätze' }}</td>
            <td>{{ $event->datetime_start?->format('d.m.Y H:i') }}</td>
            <td>{{ $event->datetime_end?->format('d.m.Y H:i') }}</td>
            <td>{{ $event->status }}</td>
            <td>
                <a href="{{ route('admin.events.edit', $event) }}">Bearbeiten</a>
                <form method="POST" action="{{ route('admin.events.destroy', $event) }}" onsubmit="return confirm('Veranstaltung löschen?')" style="display:inline">
                    @method('DELETE') @csrf
                    <button type="submit" class="abmelden-button default-button">Löschen</button>
                </form>
            </td>
        </tr>
    @endforeach
    </tbody></table>
@endsection
