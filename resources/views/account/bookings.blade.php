@extends('layouts.app')
@section('title', __('booking.account.my_bookings'))

@section('content')
<div class="panel" style="max-width:820px; margin:32px auto; padding:28px 32px;">
    <h1 style="font-size:20px; color:#C84B11; margin:0 0 24px 0;">{{ __('booking.account.my_bookings') }}</h1>

    @if($bookings->isEmpty())
        <p style="color:#888;">{{ __('booking.messages.no_active_bookings') }}</p>
    @else
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">{{ __('booking.account.court') }}</th>
                    <th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">{{ __('booking.account.date') }}</th>
                    <th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">{{ __('booking.account.time') }}</th>
                    <th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">{{ __('booking.account.status') }}</th>
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
                                  onsubmit="return confirm('{{ __('booking.messages.confirm_cancel_booking') }}')" style="margin:0;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="default-button abmelden-button">{{ __('booking.account.cancel') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
