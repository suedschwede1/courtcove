@extends('layouts.admin')
@section('admin-title', __('booking.admin.bookings.title'))
@section('admin-content')
<div class="flex flex-col gap-6">

    <h1 class="text-2xl font-bold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.bookings.title') }} #{{ $booking->bid }}</h1>

    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.bookings.title') }}</h2>
        </div>
        <div class="px-6 py-5">
            <dl class="divide-y divide-[#f0ede6]">
                <div class="flex py-3 gap-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] w-32 shrink-0">{{ __('booking.admin.bookings.booked_for') }}</dt>
                    <dd class="text-sm text-[#151515]">{{ $booking->owner_label }}</dd>
                </div>
                <div class="flex py-3 gap-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] w-32 shrink-0">{{ __('booking.admin.common.court') }}</dt>
                    <dd class="text-sm text-[#151515]">{{ $booking->square?->display_name ?? '—' }}</dd>
                </div>
                <div class="flex py-3 gap-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] w-32 shrink-0">{{ __('booking.admin.common.status') }}</dt>
                    <dd class="text-sm text-[#151515]">{{ $booking->status }}</dd>
                </div>
                <div class="flex py-3 gap-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] w-32 shrink-0">{{ __('booking.admin.common.player') }}</dt>
                    <dd class="text-sm text-[#151515]">{{ $booking->player_names !== [] ? implode(', ', $booking->player_names) : '—' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.bookings.reservations') }}</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.common.date') }}</th>
                        <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.common.from') }}</th>
                        <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.common.to') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($booking->reservations as $reservation)
                    <tr class="hover:bg-[#fafaf9]">
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $reservation->date }}</td>
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $reservation->time_start }}</td>
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $reservation->time_end }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.bookings.edit', $booking) }}" class="text-sm text-[#bf4316] hover:underline">{{ __('booking.admin.bookings.edit_title') }}</a>

        @if($booking->status !== 'cancelled')
            <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" onsubmit="return confirm({{ Js::from(__('booking.admin.bookings.confirm_cancel')) }})">
                @csrf
                <button type="submit" class="border border-[#d1cbc0] text-[#6a6e73] text-sm px-4 py-2 rounded hover:bg-[#f9f8f6] transition-colors">{{ __('booking.admin.common.cancel') }}</button>
            </form>
        @endif

        <form method="POST" action="{{ route('admin.bookings.destroy', $booking) }}" onsubmit="return confirm({{ Js::from(__('booking.admin.bookings.confirm_delete')) }})">
            @method('DELETE') @csrf
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm px-4 py-2 rounded transition-colors">{{ __('booking.admin.bookings.delete_permanent') }}</button>
        </form>

        <a href="{{ route('admin.bookings.index') }}" class="text-sm text-[#6a6e73] hover:underline">{{ __('booking.admin.common.back') }}</a>
    </div>

</div>
@endsection
