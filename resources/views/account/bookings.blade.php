@extends('layouts.app')
@section('title', __('booking.account.my_bookings'))

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8 ui-page">
    <div class="ui-page-header">
        <h1>{{ __('booking.account.my_bookings') }}</h1>
        <p>{{ __('booking.account.bookings_intro') }}</p>
    </div>

    @if(session('success'))
        <div class="ui-flash ui-flash-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="ui-flash ui-flash-error">{{ session('error') }}</div>
    @endif

    <div class="ui-card">
        @if($bookings->isEmpty())
            <div class="ui-card-body text-center">
                <p class="ui-kpi-meta">{{ __('booking.messages.no_active_bookings') }}</p>
                <div class="mt-4"><a href="{{ route('calendar.index') }}" class="ui-btn ui-btn-outline">{{ __('booking.account.to_calendar') }}</a></div>
            </div>
        @else
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>{{ __('booking.account.court') }}</th>
                            <th>{{ __('booking.account.date') }}</th>
                            <th>{{ __('booking.account.time') }}</th>
                            <th>{{ __('booking.account.status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bookings as $booking)
                            @php($reservation = $booking->reservations->first())
                            <tr>
                                <td>{{ $booking->square?->display_name }}</td>
                                <td class="text-[#6a6e73]">{{ $reservation?->date }}</td>
                                <td class="text-[#6a6e73]">
                                    @if($reservation)
                                        {{ \Illuminate\Support\Str::of($reservation->time_start)->substr(0, 5) }}-{{ \Illuminate\Support\Str::of($reservation->time_end)->substr(0, 5) }} {{ __('booking.admin.common.clock_suffix') }}
                                    @endif
                                </td>
                                <td>
                                    <span class="ui-badge {{ $booking->status === 'cancelled' ? 'ui-badge-danger' : ($booking->status === 'subscription' ? 'ui-badge-info' : 'ui-badge-success') }}">
                                        {{ $booking->status === 'cancelled' ? __('booking.account.status_cancelled') : ($booking->status === 'subscription' ? __('booking.account.status_series') : __('booking.account.status_active')) }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('bookings.destroy', $booking) }}" onsubmit="return confirm({{ Js::from(__('booking.messages.confirm_cancel_booking')) }})" class="m-0">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-btn ui-btn-danger">{{ __('booking.account.cancel') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

