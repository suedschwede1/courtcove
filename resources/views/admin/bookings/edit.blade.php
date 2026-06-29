@extends('layouts.admin')
@section('admin-title', ($isCreate ?? false) ? __('booking.admin.bookings.create_title') : __('booking.admin.bookings.edit_title'))
@section('admin-content')
@php
    $bookingUser = $booking->user;
    $userStatusLabel = $bookingUser?->status ? ucfirst($bookingUser->status) : '—';
    $userEmail = $bookingUser?->email ?: __('booking.admin.bookings.no_email');
    $userPhone = $bookingUser?->getMeta('phone');
    $createdLabel = $booking->created ? \Carbon\Carbon::parse($booking->created)->format('d.m.Y \u\m H:i \U\h\r') : __('booking.admin.bookings.not_saved_yet');
    $ownerName = trim((string) ($booking->meta->firstWhere('key', 'owner-name')?->value ?? ''));
    $isMemberOwner = $ownerName === '' && $bookingUser !== null;
    $memberLabel = $bookingUser ? $bookingUser->alias . ' (' . $bookingUser->uid . ')' : '—';
    $isCreateMode = (bool) ($isCreate ?? false);
    $closeRoute = $isCreateMode ? route('calendar.index', ['date' => old('date', $reservation?->date)]) : route('admin.bookings.index');
    $formAction = $isCreateMode ? route('admin.bookings.store') : route('admin.bookings.update', $booking);
    $formId = $isCreateMode ? 'admin-booking-create' : 'admin-booking-update';
@endphp

@if($isCreateMode)
<div class="inline-flex gap-1 mb-4" id="type-switcher">
    <button type="button" class="admin-type-switcher__tab admin-type-switcher__tab--active px-4 py-2 text-sm font-medium rounded transition-colors" data-tab="booking">{{ __('booking.admin.bookings.type_booking') }}</button>
    @can('admin.event')
    <button type="button" class="admin-type-switcher__tab px-4 py-2 text-sm font-medium rounded transition-colors" data-tab="event">{{ __('booking.admin.bookings.type_event') }}</button>
    @endcan
</div>
@endif

<form method="POST" action="{{ $formAction }}" class="flex flex-col gap-6" id="{{ $formId }}">
    @csrf
    @unless($isCreateMode)
        @method('PUT')
    @endunless
    @if(request('popup'))
        <input type="hidden" name="popup" value="1">
        <input type="hidden" name="redirect_to" value="{{ route('calendar.index', ['date' => old('date', $reservation?->date ?? now()->format('Y-m-d'))]) }}">
    @endif

    @if($isCreateMode)

    {{-- Row 1: Platz | Gebucht für --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-[#f0ede6]">
                <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.common.court') }}</h2>
            </div>
            <div class="px-6 py-5 flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="sid">{{ __('booking.admin.common.court') }}</label>
                    <select name="sid" id="sid" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        @foreach($squares as $square)
                            <option value="{{ $square->sid }}" @selected(old('sid', $booking->sid) == $square->sid)>{{ $square->display_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-[#f0ede6]">
                <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.bookings.booked_for') }}</h2>
            </div>
            <div class="px-6 py-5 flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="booked_for">{{ __('booking.admin.bookings.booked_for') }}</label>
                    <input type="text" id="booked_for" name="booked_for" value="{{ old('booked_for', $bookedFor) }}"
                           list="admin-player-suggestions" maxlength="120" required
                           class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>
            </div>
        </div>
    </div>

    {{-- Row 2: Zeit + Datum + Wiederholung | Spieleranzahl + Spielernamen --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {{-- Zeit / Datum --}}
        <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-[#f0ede6]">
                <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.bookings.time_section') }}</h2>
            </div>
            <div class="px-6 py-5 flex flex-col gap-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="date">{{ __('booking.admin.bookings.date_start') }}</label>
                        <input type="date" id="date" name="date" value="{{ old('date', $reservation?->date) }}"
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="admin-booking-date-end">{{ __('booking.admin.bookings.date_end') }}</label>
                        <input type="date" id="admin-booking-date-end" name="date_end" value="{{ old('date_end', $repeatEndDate) }}"
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="time_start">{{ __('booking.admin.bookings.time_start') }}</label>
                        <input type="time" id="time_start" name="time_start" value="{{ old('time_start', $reservation ? substr((string) $reservation->time_start, 0, 5) : '') }}"
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="time_end">{{ __('booking.admin.bookings.time_end') }}</label>
                        <input type="time" id="time_end" name="time_end" value="{{ old('time_end', $reservation ? substr((string) $reservation->time_end, 0, 5) : '') }}"
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="admin-booking-repeat">{{ __('booking.admin.bookings.repeat') }}</label>
                    <select name="repeat_type" id="admin-booking-repeat" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        @foreach($repeatOptions as $repeatValue => $repeatLabel)
                            <option value="{{ $repeatValue }}" @selected(old('repeat_type', $repeatType) === $repeatValue)>{{ $repeatLabel }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Spieleranzahl + Spielernamen --}}
        <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-[#f0ede6]">
                <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.bookings.player_names') }}</h2>
            </div>
            <div class="px-6 py-5 flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="admin-booking-quantity">{{ __('booking.admin.bookings.player_count') }}</label>
                    <select name="quantity" id="admin-booking-quantity" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        <option value="2" @selected((int) old('quantity', $booking->quantity) === 2)>2</option>
                        <option value="4" @selected((int) old('quantity', $booking->quantity) === 4)>4</option>
                    </select>
                </div>
                <div class="flex flex-col gap-4">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.bookings.player_names') }}</span>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="admin-player2">2.</label>
                        <input type="text" id="admin-player2" name="player_name_2" value="{{ old('player_name_2', $playerNames[2]) }}"
                               list="admin-player-suggestions" maxlength="120"
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                    <div id="admin-player3-field" class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="admin-player3">3.</label>
                        <input type="text" id="admin-player3" name="player_name_3" value="{{ old('player_name_3', $playerNames[3]) }}"
                               list="admin-player-suggestions" maxlength="120"
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                    <div id="admin-player4-field" class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="admin-player4">4.</label>
                        <input type="text" id="admin-player4" name="player_name_4" value="{{ old('player_name_4', $playerNames[4]) }}"
                               list="admin-player-suggestions" maxlength="120"
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 3: Notizen --}}
    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.bookings.notes_section') }}</h2>
        </div>
        <div class="px-6 py-5 flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="admin_note">{{ __('booking.admin.bookings.notes_section') }}</label>
                <textarea id="admin_note" name="admin_note" rows="3"
                          class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">{{ old('admin_note', $adminNote) }}</textarea>
                <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.bookings.note_hint') }}</p>
            </div>
        </div>
    </div>

    <datalist id="admin-player-suggestions">
        @foreach($users as $user)
            <option value="{{ $user->alias }}"></option>
        @endforeach
    </datalist>

    @else
    {{-- Edit mode: vertical card layout --}}

    {{-- Gebucht für --}}
    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.bookings.booked_for') }}</h2>
        </div>
        <div class="px-6 py-5 flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="booked_for">{{ __('booking.admin.bookings.booked_for') }}</label>
                <input type="text" id="booked_for" name="booked_for" value="{{ old('booked_for', $bookedFor) }}"
                       list="admin-player-suggestions" maxlength="120" required
                       class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
            </div>
            @if($isMemberOwner && Route::has('admin.users.edit') && auth()->user()->can('admin.user'))
            <div>
                <a href="{{ route('admin.users.edit', $bookingUser) }}" class="text-sm text-[#bf4316] hover:underline">{{ __('booking.admin.bookings.edit_user') }}</a>
            </div>
            @endif
        </div>
    </div>

    {{-- Buchung --}}
    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.bookings.booking_section') }}</h2>
        </div>
        <div class="px-6 py-5 flex flex-col gap-4">
            <div class="flex flex-wrap gap-4 items-end">
                <div class="flex flex-col gap-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.common.court') }}</span>
                    <select name="sid" id="sid" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        @foreach($squares as $square)
                            <option value="{{ $square->sid }}" @selected(old('sid', $booking->sid) == $square->sid)>{{ $square->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.bookings.player_count') }}</span>
                    <select name="quantity" id="admin-booking-quantity" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        <option value="2" @selected((int) old('quantity', $booking->quantity) === 2)>2</option>
                        <option value="4" @selected((int) old('quantity', $booking->quantity) === 4)>4</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.bookings.booking_status') }}</span>
                    <select name="status" id="status" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        <option value="single" @selected(old('status', $booking->status) === 'single')>{{ __('booking.admin.bookings.status_active') }}</option>
                        <option value="subscription" @selected(old('status', $booking->status) === 'subscription')>{{ __('booking.admin.bookings.status_series') }}</option>
                        <option value="cancelled" @selected(old('status', $booking->status) === 'cancelled')>{{ __('booking.admin.bookings.status_cancelled') }}</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Zeit --}}
    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.bookings.time_section') }}</h2>
        </div>
        <div class="px-6 py-5 flex flex-col gap-4">
            <div class="flex flex-wrap gap-4 items-end">
                <div class="flex flex-col gap-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.bookings.date_start') }}</span>
                    <input type="date" id="date" name="date" value="{{ old('date', $reservation?->date) }}"
                           class="border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.bookings.date_end') }}</span>
                    <input type="date" id="admin-booking-date-end" name="date_end" value="{{ old('date_end', $repeatEndDate) }}"
                           class="border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.bookings.time_start') }}</span>
                    <input type="time" id="time_start" name="time_start" value="{{ old('time_start', $reservation ? substr((string) $reservation->time_start, 0, 5) : '') }}"
                           class="border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.bookings.time_end') }}</span>
                    <input type="time" id="time_end" name="time_end" value="{{ old('time_end', $reservation ? substr((string) $reservation->time_end, 0, 5) : '') }}"
                           class="border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="admin-booking-repeat">{{ __('booking.admin.bookings.repeat') }}</label>
                <select name="repeat_type" id="admin-booking-repeat" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    @foreach($repeatOptions as $repeatValue => $repeatLabel)
                        <option value="{{ $repeatValue }}" @selected(old('repeat_type', $repeatType) === $repeatValue)>{{ $repeatLabel }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Spielernamen --}}
    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.bookings.player_names') }}</h2>
        </div>
        <div class="px-6 py-5 flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="admin-player2">2.</label>
                <input type="text" id="admin-player2" name="player_name_2" value="{{ old('player_name_2', $playerNames[2]) }}"
                       list="admin-player-suggestions" maxlength="120" required
                       class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
            </div>
            <div class="flex flex-col gap-1" id="admin-player3-field">
                <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="admin-player3">3.</label>
                <input type="text" id="admin-player3" name="player_name_3" value="{{ old('player_name_3', $playerNames[3]) }}"
                       list="admin-player-suggestions" maxlength="120"
                       class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
            </div>
            <div class="flex flex-col gap-1" id="admin-player4-field">
                <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="admin-player4">4.</label>
                <input type="text" id="admin-player4" name="player_name_4" value="{{ old('player_name_4', $playerNames[4]) }}"
                       list="admin-player-suggestions" maxlength="120"
                       class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
            </div>
            <datalist id="admin-player-suggestions">
                @foreach($users as $user)<option value="{{ $user->alias }}"></option>@endforeach
            </datalist>
        </div>
    </div>

    {{-- Notizen --}}
    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.bookings.notes_section') }}</h2>
        </div>
        <div class="px-6 py-5 flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="admin_note">{{ __('booking.admin.bookings.notes_section') }}</label>
                <textarea id="admin_note" name="admin_note" rows="6"
                          class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">{{ old('admin_note', $adminNote) }}</textarea>
                <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.bookings.note_hint') }}</p>
                <p class="text-xs text-[#6a6e73]">{{ __('booking.admin.bookings.booking_created_at', ['date' => $createdLabel]) }}</p>
            </div>
        </div>
    </div>
    @endif

    @if($isCreateMode)
    <div class="flex flex-wrap gap-3 items-center pt-4">
        <button type="submit" class="bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium px-5 py-2 rounded transition-colors">{{ __('booking.admin.common.save') }}</button>
        <a href="{{ $closeRoute }}" class="border border-[#d1cbc0] text-[#6a6e73] text-sm px-4 py-2 rounded hover:bg-[#f9f8f6] transition-colors">{{ __('booking.admin.common.abort') }}</a>
    </div>
    @endif
</form>

@unless($isCreateMode)
    {{-- Actions --}}
    <div class="flex flex-wrap gap-3 items-center pt-4">
        <button type="submit" form="{{ $formId }}" class="bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium px-5 py-2 rounded transition-colors">{{ __('booking.admin.common.save') }}</button>
        <a href="{{ $closeRoute }}" class="border border-[#d1cbc0] text-[#6a6e73] text-sm px-4 py-2 rounded hover:bg-[#f9f8f6] transition-colors">{{ __('booking.admin.common.abort') }}</a>

        @if($booking->status !== 'cancelled')
            <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" onsubmit="return confirm({{ Js::from(__('booking.admin.bookings.confirm_cancel')) }})">
                @csrf
                @if(request('popup'))
                    <input type="hidden" name="redirect_to" value="{{ route('calendar.index', ['date' => old('date', $reservation?->date ?? now()->format('Y-m-d'))]) }}">
                @endif
                <button type="submit" class="border border-red-300 text-red-600 text-sm px-4 py-2 rounded hover:bg-red-50 transition-colors">{{ __('booking.admin.bookings.cancel_booking') }}</button>
            </form>
        @endif
        <form method="POST" action="{{ route('admin.bookings.destroy', $booking) }}" onsubmit="return confirm({{ Js::from(__('booking.admin.bookings.confirm_delete')) }})">
            @csrf
            @method('DELETE')
            @if(request('popup'))
                <input type="hidden" name="redirect_to" value="{{ route('calendar.index', ['date' => old('date', $reservation?->date ?? now()->format('Y-m-d'))]) }}">
            @endif
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm px-4 py-2 rounded transition-colors">{{ __('booking.admin.common.delete') }}</button>
        </form>
    </div>
@endunless

@if($isCreateMode ?? false)
@can('admin.event')
<div id="panel-event" hidden>
    <form method="POST" action="{{ route('admin.events.store') }}" class="flex flex-col gap-4" id="admin-event-create">
        @csrf
        @if(request('popup'))
        <input type="hidden" name="popup" value="1">
        <input type="hidden" name="redirect_to" value="{{ route('calendar.index') }}">
        @endif
        @include('admin.events._form', array_merge($eventFormData ?? [], ['squares' => $squares]))
        <div class="flex flex-wrap gap-3 items-center pt-4">
            <button type="submit" class="bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium px-5 py-2 rounded transition-colors">{{ __('booking.admin.common.create') }}</button>
            <a href="{{ $closeRoute }}" class="border border-[#d1cbc0] text-[#6a6e73] text-sm px-4 py-2 rounded hover:bg-[#f9f8f6] transition-colors">{{ __('booking.admin.common.abort') }}</a>
        </div>
    </form>
</div>
@endcan
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    var quantity = document.getElementById('admin-booking-quantity');
    var repeat = document.getElementById('admin-booking-repeat');
    var dateEnd = document.getElementById('admin-booking-date-end');
    var dateStart = document.querySelector('input[name="date"]');
    var player3Field = document.getElementById('admin-player3-field');
    var player4Field = document.getElementById('admin-player4-field');
    var player3Input = document.getElementById('admin-player3');
    var player4Input = document.getElementById('admin-player4');

    function syncAdminBookingFields() {
        if (quantity) {
            var isDouble = quantity.value === '4';

            if (player3Field) {
                player3Field.style.display = isDouble ? '' : 'none';
            }

            if (player4Field) {
                player4Field.style.display = isDouble ? '' : 'none';
            }

            if (player3Input) {
                player3Input.required = isDouble;
                if (!isDouble) {
                    player3Input.value = '';
                }
            }

            if (player4Input) {
                player4Input.required = isDouble;
                if (!isDouble) {
                    player4Input.value = '';
                }
            }
        }

        if (repeat && dateEnd) {
            var isOnce = repeat.value === 'once';
            dateEnd.readOnly = isOnce;
            dateEnd.classList.toggle('admin-booking-input--readonly', isOnce);
            if (isOnce && dateStart) {
                dateEnd.value = dateStart.value;
            }
        }
    }

    if (quantity) {
        quantity.addEventListener('change', syncAdminBookingFields);
    }

    if (repeat) {
        repeat.addEventListener('change', syncAdminBookingFields);
    }

    if (dateStart) {
        dateStart.addEventListener('change', syncAdminBookingFields);
    }

    syncAdminBookingFields();

    // Tab switching (Buchung ↔ Veranstaltung) without page reload
    var typeSwitcher = document.getElementById('type-switcher');
    var bookingForm  = document.getElementById('admin-booking-create');
    var eventPanel   = document.getElementById('panel-event');

    if (typeSwitcher) {
        typeSwitcher.querySelectorAll('[data-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tab = btn.getAttribute('data-tab');
                typeSwitcher.querySelectorAll('[data-tab]').forEach(function (b) {
                    b.classList.toggle('admin-type-switcher__tab--active', b === btn);
                });
                if (bookingForm) { bookingForm.hidden = tab !== 'booking'; }
                if (eventPanel)  { eventPanel.hidden  = tab !== 'event';   }
            });
        });
    }
});
</script>
@endsection
