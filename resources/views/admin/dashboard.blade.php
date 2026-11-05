@extends('layouts.admin')
@section('admin-title', __('booking.admin.overview'))
@section('admin-content')
@php
    $weekDiff = $bookingsThisWeek - $bookingsLastWeek;
    $bookingsTodayDetail = !empty($bookingsTodayBySquare)
        ? collect($bookingsTodayBySquare)->map(function ($count, $name) { return $name . ': ' . $count; })->implode(' · ')
        : __('booking.admin.dashboard.no_bookings');
    $todaySorted = $bookingsToday->sortBy(function ($booking) {
        return $booking->reservations->first()?->time_start;
    });
    $adminMeta = $adminCount . ' ' . ($adminCount === 1 ? __('booking.admin.dashboard.administrator_singular') : __('booking.admin.dashboard.administrator_plural'));
    if ($weekDiff > 0) {
        $weekMeta = __('booking.admin.dashboard.week_more', ['count' => $weekDiff]);
    } elseif ($weekDiff < 0) {
        $weekMeta = __('booking.admin.dashboard.week_less', ['count' => abs($weekDiff)]);
    } else {
        $weekMeta = __('booking.admin.dashboard.week_equal');
    }
    $nextEventName = $nextEvent
        ? ($nextEvent->meta->firstWhere('key', 'name')?->value ?? __('booking.admin.dashboard.next_event_fallback'))
        : null;
    $nextEventStart = $nextEvent ? \Carbon\Carbon::parse($nextEvent->datetime_start) : null;
    $nextEventEnd = $nextEvent ? \Carbon\Carbon::parse($nextEvent->datetime_end) : null;
    $eventsMeta = $nextEvent
        ? __('booking.admin.dashboard.next_event_from', ['name' => $nextEventName, 'date' => $nextEventStart->translatedFormat('j. M H:i')])
        : __('booking.admin.dashboard.none_upcoming');
    $labelTime = __('booking.admin.dashboard.time');
    $labelCourt = __('booking.admin.dashboard.court');
    $labelMember = __('booking.admin.dashboard.member');
    $labelStatus = __('booking.admin.dashboard.status');
@endphp
<div class="ds-admin-stack">
    <x-admin.page-header :title="__('booking.admin.overview')" :eyebrow="now()->translatedFormat('l, j. F Y')">
        @can('admin.booking')
            <x-slot:actions>
                <x-ui.button :href="route('admin.bookings.create')" icon="plus" :block="false" class="ds-admin-fab">{{ __('booking.admin.dashboard.new_booking') }}</x-ui.button>
            </x-slot:actions>
        @endcan
    </x-admin.page-header>

    <div class="ds-admin-kpis">
        <x-ui.card as="div" class="ds-admin-kpi">
            <x-ui.kpi :label="__('booking.admin.dashboard.bookings_today')" :value="$bookingsTodayCount" :meta="$bookingsTodayDetail" />
        </x-ui.card>
        <x-ui.card as="div" class="ds-admin-kpi">
            <x-ui.kpi :label="__('booking.admin.dashboard.active_members')" :value="$activeMembersCount" :meta="$adminMeta" />
        </x-ui.card>
        <x-ui.card as="div" class="ds-admin-kpi">
            <x-ui.kpi :label="__('booking.admin.dashboard.this_week')" :value="$bookingsThisWeek" :meta="$weekMeta" />
        </x-ui.card>
        <x-ui.card as="div" class="ds-admin-kpi">
            <x-ui.kpi :label="__('booking.admin.dashboard.events')" :value="$upcomingEventsCount" :meta="$eventsMeta" />
        </x-ui.card>
    </div>

    <div class="ds-admin-grid">
        <x-ui.card :title="__('booking.admin.dashboard.bookings_today')" padding="none" class="ds-admin-grid__main ds-admin-rows ds-admin-today">
            @can('admin.booking')
                <x-slot:actions>
                    <a href="{{ route('admin.bookings.index') }}" class="ds-admin-link">{{ __('booking.admin.dashboard.all_bookings') }}</a>
                </x-slot:actions>
            @endcan
            @if($bookingsToday->isEmpty())
                <p class="ds-admin-note">{{ __('booking.admin.dashboard.none_today') }}</p>
            @else
                <x-ui.table :caption="__('booking.admin.dashboard.bookings_today')">
                    <x-slot:head>
                        <tr>
                            <th scope="col">{{ $labelTime }}</th>
                            <th scope="col">{{ $labelCourt }}</th>
                            <th scope="col">{{ $labelMember }}</th>
                            <th scope="col">{{ $labelStatus }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach($todaySorted as $booking)
                        @php
                            $res = $booking->reservations->first();
                            $timeStart = $res ? substr($res->time_start, 0, 5) : __('booking.account.empty_option');
                            $timeEnd = $res ? substr($res->time_end, 0, 5) : '';
                        @endphp
                        <tr>
                            <td class="ds-cell--title ds-admin-today__time" data-label="{{ $labelTime }}">{{ $timeStart }}@if($timeEnd)<span class="ds-admin-today__end"> – {{ $timeEnd }}</span>@endif</td>
                            <td class="ds-admin-today__court" data-label="{{ $labelCourt }}">{{ $booking->square?->display_name ?? __('booking.account.empty_option') }}</td>
                            <td class="ds-admin-today__member" data-label="{{ $labelMember }}">{{ $booking->owner_label }}</td>
                            <td class="ds-admin-today__status" data-label="{{ $labelStatus }}">
                                @if($booking->isCancelled())
                                    <x-ui.badge status="cancelled">{{ __('booking.admin.bookings.status_cancelled') }}</x-ui.badge>
                                @else
                                    <x-ui.badge status="active">{{ __('booking.admin.bookings.status_active') }}</x-ui.badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('booking.admin.dashboard.next_events')" class="ds-admin-grid__side">
            @if($nextEvent)
                <ul class="ds-admin-list">
                    <li class="ds-admin-list__item">
                        <p class="ds-admin-list__title">{{ $nextEventName }}</p>
                        <p class="ds-admin-note">{{ $nextEventStart->translatedFormat('D, j. M H:i') }} – {{ $nextEventEnd->format('H:i') }}@if($nextEvent->square) · {{ $nextEvent->square->display_name }}@endif</p>
                    </li>
                </ul>
            @else
                <p class="ds-admin-note">{{ __('booking.admin.dashboard.none_upcoming') }}</p>
            @endif
            @can('admin.event')
                <a href="{{ route('admin.events.index') }}" class="ds-admin-link">{{ __('booking.admin.dashboard.manage_events') }}</a>
            @endcan
        </x-ui.card>
    </div>
</div>
@endsection
