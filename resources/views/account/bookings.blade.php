@extends('layouts.app')
@section('title', __('booking.account.my_bookings'))

@section('content')
<div class="max-w-2xl mx-auto px-4 py-8 flex flex-col gap-6">

    <h1 class="text-2xl font-bold text-[#151515]"
        style="font-family: var(--font-display)">{{ __('booking.account.my_bookings') }}</h1>

    {{-- Flash-Meldungen --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg px-4 py-3">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">

        @if($bookings->isEmpty())
            <div class="px-6 py-10 text-center flex flex-col items-center gap-3">
                <p class="text-sm text-[#6a6e73]">{{ __('booking.messages.no_active_bookings') }}</p>
                <a href="{{ route('calendar.index') }}"
                   class="text-sm text-[#bf4316] hover:underline">→ Zum Kalender</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.account.court') }}</th>
                            <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.account.date') }}</th>
                            <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.account.time') }}</th>
                            <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.account.status') }}</th>
                            <th class="px-4 py-3 border-b border-[#e0ddd7]"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bookings as $booking)
                            @php($reservation = $booking->reservations->first())
                            <tr class="hover:bg-[#fafaf9]">
                                <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">
                                    {{ $booking->square?->display_name }}
                                </td>
                                <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6] whitespace-nowrap">
                                    {{ $reservation?->date }}
                                </td>
                                <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6] whitespace-nowrap">
                                    @if($reservation)
                                        {{ \Illuminate\Support\Str::of($reservation->time_start)->substr(0, 5) }}–{{ \Illuminate\Support\Str::of($reservation->time_end)->substr(0, 5) }} Uhr
                                    @endif
                                </td>
                                <td class="px-4 py-3 border-b border-[#f0ede6]">
                                    <span class="inline-block bg-green-50 text-green-700 text-xs rounded-full px-2 py-0.5">
                                        {{ $booking->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 border-b border-[#f0ede6] text-right">
                                    <form method="POST" action="{{ route('bookings.destroy', $booking) }}"
                                          onsubmit="return confirm('{{ __('booking.messages.confirm_cancel_booking') }}')"
                                          class="m-0">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="text-xs text-red-600 hover:text-red-800 hover:underline transition-colors">
                                            {{ __('booking.account.cancel') }}
                                        </button>
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
