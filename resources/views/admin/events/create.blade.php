@extends('layouts.admin')
@section('admin-title', __('booking.admin.events.create_title'))
@section('admin-content')
<div class="flex flex-col gap-6">
    @unless(request('popup'))
    <h1 class="text-2xl font-bold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.events.create_title') }}</h1>
    @endunless

    @php
        $bookingUrl = route('admin.bookings.create', array_filter([
            'sid'        => request('sid'),
            'date'       => request('date_start'),
            'time_start' => request('time_start'),
            'time_end'   => request('time_end'),
            'popup'      => request('popup') ?: null,
        ]));
    @endphp

    <div class="flex gap-2 mb-4">
        <a href="{{ $bookingUrl }}" class="inline-block px-4 py-2 text-sm font-medium border border-[#d1cbc0] text-[#6a6e73] rounded hover:bg-[#f9f8f6] transition-colors">{{ __('booking.admin.bookings.type_booking') }}</a>
        <span class="inline-block px-4 py-2 text-sm font-medium bg-[#bf4316] text-white rounded">{{ __('booking.admin.bookings.type_event') }}</span>
    </div>

    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.events.create_title') }}</h2>
        </div>
        <div class="px-6 py-5">
            <form method="POST" action="{{ route('admin.events.store') }}">
                @if(request('popup'))
                <input type="hidden" name="popup" value="1">
                <input type="hidden" name="redirect_to" value="{{ route('calendar.index') }}">
                @endif
                @include('admin.events._form', ['squares' => $squares])
                <div class="mt-6">
                    <button type="submit" class="bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium px-5 py-2 rounded transition-colors">{{ __('booking.admin.common.create') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
