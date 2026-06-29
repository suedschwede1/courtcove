@extends('layouts.admin')
@section('admin-title', __('booking.admin.bookings.title'))
@section('admin-content')
<div class="flex flex-col gap-6">

    <h1 class="text-2xl font-bold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.bookings.title') }}</h1>

    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <form method="GET" action="{{ route('admin.bookings.index') }}" class="flex flex-wrap gap-3 items-end">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.common.court') }}</label>
                    <select name="sid" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        <option value="">{{ __('booking.admin.common.all_courts') }}</option>
                        @foreach($squares as $square)
                            <option value="{{ $square->sid }}" @selected(request('sid') == $square->sid)>{{ $square->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium px-5 py-2 rounded transition-colors">{{ __('booking.admin.common.filter') }}</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.common.member') }}</th>
                        <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.common.court') }}</th>
                        <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.common.date') }}</th>
                        <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.common.time') }}</th>
                        <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.common.player') }}</th>
                        <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.common.status') }}</th>
                        <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap"></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($bookings as $b)
                    @php($reservation = $b->reservations->sortBy(['date', 'time_start'])->first())
                    <tr class="hover:bg-[#fafaf9]">
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $b->owner_label }}</td>
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $b->square?->display_name ?? '—' }}</td>
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $reservation ? \Carbon\Carbon::parse($reservation->date)->format('d.m.Y') : '—' }}</td>
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $reservation ? substr((string) $reservation->time_start, 0, 5) . ' - ' . substr((string) $reservation->time_end, 0, 5) : '—' }}</td>
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $b->player_names !== [] ? implode(', ', $b->player_names) : '—' }}</td>
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $b->status }}</td>
                        <td class="text-sm px-4 py-3 border-b border-[#f0ede6] whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('admin.bookings.edit', $b) }}" class="text-sm text-[#bf4316] hover:underline">{{ __('booking.admin.common.edit') }}</a>
                                @if($b->status !== 'cancelled')
                                    <form method="POST" action="{{ route('admin.bookings.cancel', $b) }}" class="inline" onsubmit="return confirm({{ Js::from(__('booking.admin.bookings.confirm_cancel')) }})">
                                        @csrf
                                        <button type="submit" class="text-xs text-[#6a6e73] hover:text-[#151515] hover:underline transition-colors">{{ __('booking.admin.common.cancel') }}</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.bookings.destroy', $b) }}" class="inline" onsubmit="return confirm({{ Js::from(__('booking.admin.bookings.confirm_delete')) }})">
                                    @method('DELETE') @csrf
                                    <button type="submit" class="text-xs text-red-600 hover:text-red-800 hover:underline transition-colors">{{ __('booking.admin.common.delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-[#f0ede6]">
            {{ $bookings->links() }}
        </div>
    </div>

</div>
@endsection
