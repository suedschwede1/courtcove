@extends('layouts.admin')
@section('admin-title', __('booking.admin.dashboard.title'))
@section('admin-content')
<div class="flex flex-col gap-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#151515]" style="font-family: var(--font-display)">Dashboard</h1>
            <p class="text-sm text-[#6a6e73] mt-1">{{ now()->translatedFormat('l, j. F Y') }}</p>
        </div>
    </div>

    {{-- 4 Stat-Karten --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- Buchungen heute --}}
        <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm p-5">
            <div class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] mb-2">Buchungen heute</div>
            <div class="text-3xl font-bold text-[#151515]" style="font-family: var(--font-display)">{{ $bookingsTodayCount }}</div>
            @if(!empty($bookingsTodayBySquare))
                <div class="text-xs text-[#6a6e73] mt-2">
                    {{ collect($bookingsTodayBySquare)->map(fn($count, $name) => "$name: $count")->implode(' · ') }}
                </div>
            @else
                <div class="text-xs text-[#6a6e73] mt-2">Keine Buchungen</div>
            @endif
        </div>

        {{-- Aktive Mitglieder --}}
        <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm p-5">
            <div class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] mb-2">Aktive Mitglieder</div>
            <div class="text-3xl font-bold text-[#151515]" style="font-family: var(--font-display)">{{ $activeMembersCount }}</div>
            <div class="text-xs text-[#6a6e73] mt-2">{{ $adminCount }} {{ $adminCount === 1 ? 'Administrator' : 'Administratoren' }}</div>
        </div>

        {{-- Diese Woche --}}
        <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm p-5">
            <div class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] mb-2">Diese Woche</div>
            <div class="text-3xl font-bold text-[#151515]" style="font-family: var(--font-display)">{{ $bookingsThisWeek }}</div>
            @php $weekDiff = $bookingsThisWeek - $bookingsLastWeek; @endphp
            <div class="text-xs mt-2 {{ $weekDiff >= 0 ? 'text-green-600' : 'text-[#bf4316]' }}">
                @if($weekDiff > 0)
                    ↑ {{ $weekDiff }} mehr als letzte Woche
                @elseif($weekDiff < 0)
                    ↓ {{ abs($weekDiff) }} weniger als letzte Woche
                @else
                    Gleich wie letzte Woche
                @endif
            </div>
        </div>

        {{-- Veranstaltungen --}}
        <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm p-5">
            <div class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] mb-2">Veranstaltungen</div>
            <div class="text-3xl font-bold text-[#151515]" style="font-family: var(--font-display)">{{ $upcomingEventsCount }}</div>
            @if($nextEvent)
                <div class="text-xs text-[#6a6e73] mt-2 truncate">
                    Nächste: {{ \Carbon\Carbon::parse($nextEvent->datetime_start)->translatedFormat('j. M, H:i') }}
                </div>
            @else
                <div class="text-xs text-[#6a6e73] mt-2">Keine anstehenden</div>
            @endif
        </div>

    </div>

    {{-- Buchungen heute – Tabelle --}}
    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6] flex items-center justify-between">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">Buchungen heute</h2>
            @if(Route::has('bookings.create'))
                <a href="{{ route('bookings.create') }}" class="bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium px-4 py-2 rounded transition-colors">+ Neue Buchung</a>
            @endif
        </div>
        <div class="overflow-x-auto">
            @if($bookingsToday->isEmpty())
                <div class="px-6 py-8 text-center text-sm text-[#6a6e73]">Heute keine Buchungen.</div>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-[#faf9f7] border-b border-[#f0ede6] text-left text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">
                            <th class="px-6 py-3">Mitglied</th>
                            <th class="px-6 py-3">Platz</th>
                            <th class="px-6 py-3">Zeit</th>
                            <th class="px-6 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0ede6]">
                        @foreach($bookingsToday->sortBy(fn($b) => $b->reservations->first()?->time_start) as $booking)
                            @php
                                $res = $booking->reservations->first();
                                $timeStart = $res ? substr($res->time_start, 0, 5) : '—';
                                $timeEnd   = $res ? substr($res->time_end,   0, 5) : '';
                                $isCancelled = $booking->isCancelled();
                            @endphp
                            <tr class="hover:bg-[#faf9f7] transition-colors">
                                <td class="px-6 py-3 font-medium text-[#151515]">
                                    {{ $booking->owner_label }}
                                </td>
                                <td class="px-6 py-3 text-[#6a6e73]">
                                    {{ $booking->square?->display_name ?? '—' }}
                                </td>
                                <td class="px-6 py-3 text-[#6a6e73]">
                                    {{ $timeStart }}@if($timeEnd) – {{ $timeEnd }}@endif
                                </td>
                                <td class="px-6 py-3">
                                    @if($isCancelled)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-50 text-[#bf4316]">Storniert</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-50 text-green-700">Aktiv</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Navigation / Quick Links --}}
    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.dashboard.title') }}</h2>
        </div>
        <div class="px-6 py-5">
            <nav>
                <ul class="flex flex-col gap-3">
                    @if(Route::has('admin.users.index'))
                        @can('admin.user')
                            <li><a href="{{ route('admin.users.index') }}" class="text-sm text-[#bf4316] hover:underline">{{ __('booking.admin.dashboard.user_mgmt_link') }}</a></li>
                        @endcan
                    @endif
                    @if(Route::has('admin.events.index'))
                        @can('admin.event')
                            <li><a href="{{ route('admin.events.index') }}" class="text-sm text-[#bf4316] hover:underline">{{ __('booking.admin.dashboard.events_link') }}</a></li>
                        @endcan
                    @endif
                    @if(Route::has('admin.bookings.index'))
                        @can('admin.booking')
                            <li><a href="{{ route('admin.bookings.index') }}" class="text-sm text-[#bf4316] hover:underline">{{ __('booking.admin.dashboard.bookings_link') }}</a></li>
                        @endcan
                    @endif
                    @if(Route::has('admin.config.edit'))
                        @can('admin.config')
                            <li><a href="{{ route('admin.config.edit') }}" class="text-sm text-[#bf4316] hover:underline">{{ __('booking.admin.dashboard.config_link') }}</a></li>
                        @endcan
                    @endif
                </ul>
            </nav>
        </div>
    </div>

</div>
@endsection
