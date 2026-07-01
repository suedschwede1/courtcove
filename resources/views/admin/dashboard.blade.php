@extends('layouts.admin')
@section('admin-title', __('booking.admin.dashboard.title'))
@section('admin-content')
@php
    $weekDiff = $bookingsThisWeek - $bookingsLastWeek;
    $bookingsTodayDetail = !empty($bookingsTodayBySquare)
        ? collect($bookingsTodayBySquare)->map(function ($count, $name) { return $name . ': ' . $count; })->implode(' · ')
        : __('booking.admin.dashboard.no_bookings');
    $todaySorted = $bookingsToday->sortBy(function ($booking) {
        return $booking->reservations->first()?->time_start;
    });
@endphp
<div class="ui-page">
    <div class="ui-page-header">
        <h1>{{ __('booking.admin.dashboard.heading') }}</h1>
        <p>{{ now()->translatedFormat('l, j. F Y') }}</p>
    </div>

    <div class="ui-grid-4">
        <div class="ui-card ui-kpi">
            <div class="ui-card-body ui-stack">
                <p class="ui-kpi-label">{{ __('booking.admin.dashboard.bookings_today') }}</p>
                <p class="ui-kpi-value">{{ $bookingsTodayCount }}</p>
                <p class="ui-kpi-meta">{{ $bookingsTodayDetail }}</p>
            </div>
        </div>

        <div class="ui-card" style="border-top:3px solid #0066cc;">
            <div class="ui-card-body ui-stack">
                <p class="ui-kpi-label">{{ __('booking.admin.dashboard.active_members') }}</p>
                <p class="ui-kpi-value">{{ $activeMembersCount }}</p>
                <p class="ui-kpi-meta">{{ $adminCount }} {{ $adminCount === 1 ? __('booking.admin.dashboard.administrator_singular') : __('booking.admin.dashboard.administrator_plural') }}</p>
            </div>
        </div>

        <div class="ui-card" style="border-top:3px solid #3e8635;">
            <div class="ui-card-body ui-stack">
                <p class="ui-kpi-label">{{ __('booking.admin.dashboard.this_week') }}</p>
                <p class="ui-kpi-value">{{ $bookingsThisWeek }}</p>
                <p class="ui-kpi-meta">
                    @if($weekDiff > 0)
                        {{ __('booking.admin.dashboard.week_more', ['count' => $weekDiff]) }}
                    @elseif($weekDiff < 0)
                        {{ __('booking.admin.dashboard.week_less', ['count' => abs($weekDiff)]) }}
                    @else
                        {{ __('booking.admin.dashboard.week_equal') }}
                    @endif
                </p>
            </div>
        </div>

        <div class="ui-card" style="border-top:3px solid #f0ab00;">
            <div class="ui-card-body ui-stack">
                <p class="ui-kpi-label">{{ __('booking.admin.dashboard.events') }}</p>
                <p class="ui-kpi-value">{{ $upcomingEventsCount }}</p>
                <p class="ui-kpi-meta">
                    @if($nextEvent)
                        {{ __('booking.admin.dashboard.next_event_from', ['name' => ($nextEvent->meta->firstWhere('key', 'name')?->value ?? __('booking.admin.dashboard.next_event_fallback')), 'date' => \Carbon\Carbon::parse($nextEvent->datetime_start)->translatedFormat('j. M H:i')]) }}
                    @else
                        {{ __('booking.admin.dashboard.none_upcoming') }}
                    @endif
                </p>
            </div>
        </div>
    </div>

    <div class="ui-card">
        <div class="ui-card-header">
            <h2>{{ __('booking.admin.dashboard.bookings_today') }}</h2>
            @can('admin.booking')
                <a href="{{ route('admin.bookings.create') }}" class="ui-btn ui-btn-primary">{{ __('booking.admin.dashboard.new_booking') }}</a>
            @endcan
        </div>
        <div class="ui-table-wrap">
            @if($bookingsToday->isEmpty())
                <div class="ui-card-body text-center">
                    <p class="ui-kpi-meta">{{ __('booking.admin.dashboard.none_today') }}</p>
                </div>
            @else
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>{{ __('booking.admin.dashboard.member') }}</th>
                            <th>{{ __('booking.admin.dashboard.court') }}</th>
                            <th>{{ __('booking.admin.dashboard.time') }}</th>
                            <th>{{ __('booking.admin.dashboard.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($todaySorted as $booking)
                            @php
                                $res = $booking->reservations->first();
                                $timeStart = $res ? substr($res->time_start, 0, 5) : __('booking.account.empty_option');
                                $timeEnd = $res ? substr($res->time_end, 0, 5) : '';
                            @endphp
                            <tr>
                                <td>{{ $booking->owner_label }}</td>
                                <td class="text-[#6a6e73]">{{ $booking->square?->display_name ?? __('booking.account.empty_option') }}</td>
                                <td class="text-[#6a6e73]">{{ $timeStart }}@if($timeEnd) – {{ $timeEnd }}@endif</td>
                                <td>
                                    <span class="ui-badge {{ $booking->isCancelled() ? 'ui-badge-danger' : 'ui-badge-success' }}">{{ $booking->isCancelled() ? __('booking.admin.bookings.status_cancelled') : __('booking.admin.bookings.status_active') }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
