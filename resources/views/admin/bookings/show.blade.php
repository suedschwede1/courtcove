@extends('layouts.admin')
@section('admin-title', 'Buchung')
@section('admin-content')
    <h1>Buchung #{{ $booking->bid }}</h1>

    <dl>
        <dt>Gebucht für</dt><dd>{{ $booking->owner_label }}</dd>
        <dt>Platz</dt><dd>{{ $booking->square?->display_name ?? '—' }}</dd>
        <dt>Status</dt><dd>{{ $booking->status }}</dd>
        <dt>Spieler</dt><dd>{{ $booking->player_names !== [] ? implode(', ', $booking->player_names) : '—' }}</dd>
    </dl>

    <h2>Reservierungen</h2>
    <table class="booking-grid">
        <thead><tr><th>Datum</th><th>Von</th><th>Bis</th></tr></thead>
        <tbody>
        @foreach($booking->reservations as $reservation)
            <tr>
                <td>{{ $reservation->date }}</td>
                <td>{{ $reservation->time_start }}</td>
                <td>{{ $reservation->time_end }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <p><a href="{{ route('admin.bookings.edit', $booking) }}">Buchung bearbeiten</a></p>

    @if($booking->status !== 'cancelled')
        <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" onsubmit="return confirm('Buchung wirklich stornieren?')">
            @csrf
            <button type="submit" class="default-button">Stornieren</button>
        </form>
    @endif

    <form method="POST" action="{{ route('admin.bookings.destroy', $booking) }}" onsubmit="return confirm('Buchung wirklich dauerhaft löschen?')">
        @method('DELETE') @csrf
        <button type="submit" class="abmelden-button default-button">Dauerhaft löschen</button>
    </form>

    <a href="{{ route('admin.bookings.index') }}">Zurück</a>
@endsection
