@extends('layouts.app')
@section('title', 'Meine Buchungen')

@section('content')
<div class="panel" style="max-width:820px; margin:32px auto; padding:28px 32px;">
    <h1 style="font-size:20px; color:#C84B11; margin:0 0 24px 0;">Meine Buchungen</h1>

    @if($bookings->isEmpty())
        <p style="color:#888;">Sie haben derzeit keine aktiven Buchungen.</p>
    @else
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Platz</th>
                    <th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Datum</th>
                    <th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Zeit</th>
                    <th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Status</th>
                    <th style="padding:8px; border-bottom:1px solid #ddd;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($bookings as $booking)
                    @php($reservation = $booking->reservations->first())
                    <tr>
                        <td style="padding:8px; border-bottom:1px solid #f0f0f0;">{{ $booking->square?->display_name }}</td>
                        <td style="padding:8px; border-bottom:1px solid #f0f0f0;">
                            {{ $reservation?->date }}
                        </td>
                        <td style="padding:8px; border-bottom:1px solid #f0f0f0;">
                            @if($reservation)
                                {{ \Illuminate\Support\Str::of($reservation->time_start)->substr(0, 5) }}–{{ \Illuminate\Support\Str::of($reservation->time_end)->substr(0, 5) }}
                            @endif
                        </td>
                        <td style="padding:8px; border-bottom:1px solid #f0f0f0;">{{ $booking->status }}</td>
                        <td style="padding:8px; border-bottom:1px solid #f0f0f0; text-align:right;">
                            <form method="POST" action="{{ route('bookings.destroy', $booking) }}"
                                  onsubmit="return confirm('Diese Buchung wirklich stornieren?')" style="margin:0;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="default-button abmelden-button">Stornieren</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
