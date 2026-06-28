@extends('layouts.admin')
@section('admin-title', __('booking.admin.bookings.title'))
@section('admin-content')
    <h1>{{ __('booking.admin.bookings.title') }} #{{ $booking->bid }}</h1>

    <dl>
        <dt>{{ __('booking.admin.bookings.booked_for') }}</dt><dd>{{ $booking->owner_label }}</dd>
        <dt>{{ __('booking.admin.common.court') }}</dt><dd>{{ $booking->square?->display_name ?? '—' }}</dd>
        <dt>{{ __('booking.admin.common.status') }}</dt><dd>{{ $booking->status }}</dd>
        <dt>{{ __('booking.admin.common.player') }}</dt><dd>{{ $booking->player_names !== [] ? implode(', ', $booking->player_names) : '—' }}</dd>
    </dl>

    <hr class="admin-separator">
    <h2>{{ __('booking.admin.bookings.reservations') }}</h2>
    <table class="admin-table">
        <thead><tr><th>{{ __('booking.admin.common.date') }}</th><th>{{ __('booking.admin.common.from') }}</th><th>{{ __('booking.admin.common.to') }}</th></tr></thead>
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

    <p><a href="{{ route('admin.bookings.edit', $booking) }}">{{ __('booking.admin.bookings.edit_title') }}</a></p>

    @if($booking->status !== 'cancelled')
        <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" onsubmit="return confirm('{{ __('booking.admin.bookings.confirm_cancel') }}')">
            @csrf
            <button type="submit" class="default-button">{{ __('booking.admin.common.cancel') }}</button>
        </form>
    @endif

    <form method="POST" action="{{ route('admin.bookings.destroy', $booking) }}" onsubmit="return confirm('{{ __('booking.admin.bookings.confirm_delete') }}')">
        @method('DELETE') @csrf
        <button type="submit" class="abmelden-button default-button">{{ __('booking.admin.bookings.delete_permanent') }}</button>
    </form>

    <a href="{{ route('admin.bookings.index') }}">{{ __('booking.admin.common.back') }}</a>
@endsection
