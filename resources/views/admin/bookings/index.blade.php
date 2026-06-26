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
        <thead><tr><th>Mitglied</th><th>Platz</th><th>Datum</th><th>Zeit</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach($bookings as $b)
            @php($reservation = $b->reservations->first())
            <tr>
                <td>{{ $b->user?->alias ?? '—' }}</td>
                <td>{{ $b->square?->display_name ?? '—' }}</td>
                <td>{{ $reservation?->date ?? '—' }}</td>
                <td>{{ $reservation?->time_start ?? '—' }}</td>
                <td>{{ $b->status }}</td>
                <td>
                    <a href="{{ route('admin.bookings.show', $b) }}">Details</a>
                    <form method="POST" action="{{ route('admin.bookings.destroy', $b) }}" onsubmit="return confirm('Buchung stornieren?')" style="display:inline">
                        @method('DELETE') @csrf
                        <button type="submit" class="abmelden-button default-button">Stornieren</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>

    {{ $bookings->links() }}
@endsection
