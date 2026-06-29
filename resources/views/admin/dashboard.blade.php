@extends('layouts.admin')
@section('admin-title', __('booking.admin.dashboard.title'))
@section('admin-content')
@php
    $weekDiff = $bookingsThisWeek - $bookingsLastWeek;
    $bookingsTodayDetail = !empty($bookingsTodayBySquare)
        ? collect($bookingsTodayBySquare)->map(function ($count, $name) { return $name . ': ' . $count; })->implode(' · ')
        : 'Keine Buchungen';
    $todaySorted = $bookingsToday->sortBy(function ($booking) {
        return $booking->reservations->first()?->time_start;
    });
@endphp
<div class="ui-page">
    <div class="ui-page-header">
        <h1>Dashboard</h1>
        <p>{{ now()->translatedFormat('l, j. F Y') }}</p>
    </div>

    <div class="ui-grid-4">
        <div class="ui-card ui-kpi">
            <div class="ui-card-body ui-stack">
                <p class="ui-kpi-label">Buchungen heute</p>
                <p class="ui-kpi-value">{{ $bookingsTodayCount }}</p>
                <p class="ui-kpi-meta">{{ $bookingsTodayDetail }}</p>
            </div>
        </div>

        <div class="ui-card" style="border-top:3px solid #0066cc;">
            <div class="ui-card-body ui-stack">
                <p class="ui-kpi-label">Aktive Mitglieder</p>
                <p class="ui-kpi-value">{{ $activeMembersCount }}</p>
                <p class="ui-kpi-meta">{{ $adminCount }} {{ $adminCount === 1 ? 'Administrator' : 'Administratoren' }}</p>
            </div>
        </div>

        <div class="ui-card" style="border-top:3px solid #3e8635;">
            <div class="ui-card-body ui-stack">
                <p class="ui-kpi-label">Diese Woche</p>
                <p class="ui-kpi-value">{{ $bookingsThisWeek }}</p>
                <p class="ui-kpi-meta">
                    @if($weekDiff > 0)
                        ↑ {{ $weekDiff }} mehr als letzte Woche
                    @elseif($weekDiff < 0)
                        ↓ {{ abs($weekDiff) }} weniger als letzte Woche
                    @else
                        Gleich wie letzte Woche
                    @endif
                </p>
            </div>
        </div>

        <div class="ui-card" style="border-top:3px solid #f0ab00;">
            <div class="ui-card-body ui-stack">
                <p class="ui-kpi-label">Veranstaltungen</p>
                <p class="ui-kpi-value">{{ $upcomingEventsCount }}</p>
                <p class="ui-kpi-meta">
                    @if($nextEvent)
                        {{ ($nextEvent->meta->firstWhere('key', 'name')?->value ?? 'Nächste Veranstaltung') . ' ab ' . \Carbon\Carbon::parse($nextEvent->datetime_start)->translatedFormat('j. M H:i') }}
                    @else
                        Keine anstehenden
                    @endif
                </p>
            </div>
        </div>
    </div>

    <div class="ui-card">
        <div class="ui-card-header">
            <h2>Buchungen heute</h2>
            @can('admin.booking')
                <a href="{{ route('admin.bookings.create') }}" class="ui-btn ui-btn-primary">+ Neue Buchung</a>
            @endcan
        </div>
        <div class="ui-table-wrap">
            @if($bookingsToday->isEmpty())
                <div class="ui-card-body text-center">
                    <p class="ui-kpi-meta">Heute keine Buchungen.</p>
                </div>
            @else
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Mitglied</th>
                            <th>Platz</th>
                            <th>Zeit</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($todaySorted as $booking)
                            @php
                                $res = $booking->reservations->first();
                                $timeStart = $res ? substr($res->time_start, 0, 5) : '—';
                                $timeEnd = $res ? substr($res->time_end, 0, 5) : '';
                            @endphp
                            <tr>
                                <td>{{ $booking->owner_label }}</td>
                                <td class="text-[#6a6e73]">{{ $booking->square?->display_name ?? '—' }}</td>
                                <td class="text-[#6a6e73]">{{ $timeStart }}@if($timeEnd) – {{ $timeEnd }}@endif</td>
                                <td>
                                    <span class="ui-badge {{ $booking->isCancelled() ? 'ui-badge-danger' : 'ui-badge-success' }}">{{ $booking->isCancelled() ? 'Storniert' : 'Aktiv' }}</span>
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
