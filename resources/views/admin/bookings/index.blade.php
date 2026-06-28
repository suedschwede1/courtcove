@extends('layouts.admin')
@section('admin-title', 'Buchungen')
@section('admin-content')
    <h1>Buchungen</h1>

    <form method="GET" action="{{ route('admin.bookings.index') }}" style="margin-bottom:1rem">
        <select name="sid">
            <option value="">Alle Plätze</option>
            @foreach($squares as $square)
                <option value="{{ $square->sid }}" @selected(request('sid') == $square->sid)>{{ $square->display_name }}</option>
            @endforeach
        </select>
        <button type="submit" class="default-button">Filtern</button>
    </form>

    <div class="calendar-wrap">
    <table class="booking-grid">
        <thead><tr><th>Mitglied</th><th>Platz</th><th>Datum</th><th>Zeit</th><th>Spieler</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach($bookings as $b)
            @php($reservation = $b->reservations->sortBy(['date', 'time_start'])->first())
            <tr>
                <td>{{ $b->owner_label }}</td>
                <td>{{ $b->square?->display_name ?? '—' }}</td>
                <td>{{ $reservation ? \Carbon\Carbon::parse($reservation->date)->format('d.m.Y') : '—' }}</td>
                <td>{{ $reservation ? substr((string) $reservation->time_start, 0, 5) . ' - ' . substr((string) $reservation->time_end, 0, 5) : '—' }}</td>
                <td>{{ $b->player_names !== [] ? implode(', ', $b->player_names) : '—' }}</td>
                <td>{{ $b->status }}</td>
                <td>
                    <a href="{{ route('admin.bookings.edit', $b) }}">Bearbeiten</a>
                    @if($b->status !== 'cancelled')
                        <form method="POST" action="{{ route('admin.bookings.cancel', $b) }}" onsubmit="return confirm('Buchung wirklich stornieren?')" style="display:inline">
                            @csrf
                            <button type="submit" class="default-button">Stornieren</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.bookings.destroy', $b) }}" onsubmit="return confirm('Buchung wirklich dauerhaft löschen?')" style="display:inline">
                        @method('DELETE') @csrf
                        <button type="submit" class="abmelden-button default-button">Löschen</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>

    {{ $bookings->links() }}
@endsection
