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
    @if($errors->has('booking'))
        <div class="ui-flash ui-flash-error">{{ $errors->first('booking') }}</div>
    @endif

    <div class="ui-card ui-card--filter mb-6">
        <div class="ui-card-body">
            <p class="ui-section-label !mb-3">{{ __('booking.account.filter_heading') }}</p>
            <form method="GET" action="{{ route('account.bookings') }}" class="ui-grid-4 items-end">
                <input type="hidden" name="searched" value="1">

                <div class="ui-field">
                    <label class="ui-label" for="booking-q">{{ __('booking.account.search') }}</label>
                    <input id="booking-q" type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="ui-input" placeholder="{{ __('booking.account.search_placeholder') }}">
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="booking-sid">{{ __('booking.account.court') }}</label>
                    <select id="booking-sid" name="sid" class="ui-select">
                        <option value="">{{ __('booking.account.all_courts') }}</option>
                        @foreach($squares as $square)
                            <option value="{{ $square->sid }}" @selected(($filters['sid'] ?? '') === (string) $square->sid)>{{ $square->display_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="booking-status">{{ __('booking.account.status') }}</label>
                    <select id="booking-status" name="status" class="ui-select">
                        <option value="active" @selected(($filters['status'] ?? 'active') === 'active')>{{ __('booking.account.status_filter_active') }}</option>
                        <option value="single" @selected(($filters['status'] ?? '') === 'single')>{{ __('booking.account.status_active') }}</option>
                        <option value="subscription" @selected(($filters['status'] ?? '') === 'subscription')>{{ __('booking.account.status_series') }}</option>
                        <option value="cancelled" @selected(($filters['status'] ?? '') === 'cancelled')>{{ __('booking.account.status_cancelled') }}</option>
                        <option value="all" @selected(($filters['status'] ?? '') === 'all')>{{ __('booking.account.status_filter_all') }}</option>
                    </select>
                </div>

                <div class="ui-row justify-end">
                    <button type="submit" class="ui-btn ui-btn-primary">{{ __('booking.account.search_button') }}</button>
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="booking-date-from">{{ __('booking.account.date_from') }}</label>
                    <input id="booking-date-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="ui-input">
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="booking-date-to">{{ __('booking.account.date_to') }}</label>
                    <input id="booking-date-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="ui-input">
                </div>

                <div class="ui-row items-end">
                    <a href="{{ route('account.bookings') }}" class="ui-btn ui-btn-ghost">{{ __('booking.account.reset_filters') }}</a>
                </div>
            </form>
        </div>
    </div>

    <div class="ui-card">
        @if(! $searched)
            <div class="ui-card-body text-center">
                <p class="ui-kpi-meta">{{ __('booking.account.search_hint') }}</p>
            </div>
        @elseif($bookings->isEmpty())
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
                            @php
                                $reservation = $booking->reservations->sortBy(fn ($reservation) => $reservation->date.' '.$reservation->time_start)->first();
                                $canCancel = in_array($booking->bid, $cancellableBookingIds, true);
                            @endphp
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
                                    @if($canCancel)
                                        <form method="POST" action="{{ route('bookings.destroy', $booking) }}" onsubmit="return confirm({{ Js::from(__('booking.messages.confirm_cancel_booking')) }})" class="m-0">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ui-btn ui-btn-danger">{{ __('booking.account.cancel') }}</button>
                                        </form>
                                    @else
                                        <button type="button" class="ui-btn ui-btn-danger is-disabled" disabled title="{{ __('booking.account.cancel_disabled') }}">{{ __('booking.account.cancel') }}</button>
                                    @endif
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