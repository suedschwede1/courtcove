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
<div class="admin-type-switcher">
    <span class="admin-type-switcher__tab admin-type-switcher__tab--active">{{ __('booking.admin.bookings.type_booking') }}</span>
    @php
        $eventUrl = route('admin.events.create', array_filter([
            'sid'        => old('sid', $booking->sid),
            'date_start' => old('date', $reservation?->date),
            'time_start' => old('time_start', $reservation ? substr((string)$reservation->time_start, 0, 5) : ''),
            'date_end'   => old('date', $reservation?->date),
            'time_end'   => old('time_end', $reservation ? substr((string)$reservation->time_end, 0, 5) : ''),
            'popup'      => request('popup') ?: null,
        ]));
    @endphp
    <a href="{{ $eventUrl }}" class="admin-type-switcher__tab">{{ __('booking.admin.bookings.type_event') }}</a>
</div>
@endif

<form method="POST" action="{{ $formAction }}" class="admin-form{{ $isCreateMode ? ' admin-form--compact' : '' }}" id="{{ $formId }}">
    @csrf
    @unless($isCreateMode)
        @method('PUT')
    @endunless

    @if($isCreateMode)
    {{-- Compact 3-card layout for create/popup mode --}}
    <div class="abf-row">

        {{-- Card 1: Gebucht für --}}
        <div class="abf-card">
            <div class="abf-field">
                <label class="abf-label" for="booked_for">{{ __('booking.admin.bookings.booked_for') }}</label>
                <input type="text" id="booked_for" name="booked_for" value="{{ old('booked_for', $bookedFor) }}"
                       list="admin-player-suggestions" maxlength="120" required class="abf-input">
            </div>
        </div>

        {{-- Card 2: Zeit + Datum + Wiederholung --}}
        <div class="abf-card">
            <div class="abf-row2">
                <div class="abf-field">
                    <label class="abf-label" for="time_start">{{ __('booking.admin.bookings.time_start') }}</label>
                    <input type="time" id="time_start" name="time_start" value="{{ old('time_start', $reservation ? substr((string) $reservation->time_start, 0, 5) : '') }}" class="abf-dateinput">
                </div>
                <div class="abf-field">
                    <label class="abf-label" for="time_end">{{ __('booking.admin.bookings.time_end') }}</label>
                    <input type="time" id="time_end" name="time_end" value="{{ old('time_end', $reservation ? substr((string) $reservation->time_end, 0, 5) : '') }}" class="abf-dateinput">
                </div>
            </div>
            <div class="abf-row2" style="margin-top:8px;">
                <div class="abf-field">
                    <label class="abf-label" for="date">{{ __('booking.admin.bookings.date_start') }}</label>
                    <input type="date" id="date" name="date" value="{{ old('date', $reservation?->date) }}" class="abf-dateinput">
                </div>
                <div class="abf-field">
                    <label class="abf-label" for="admin-booking-date-end">{{ __('booking.admin.bookings.date_end') }}</label>
                    <input type="date" id="admin-booking-date-end" name="date_end" value="{{ old('date_end', $repeatEndDate) }}" class="abf-dateinput">
                </div>
            </div>
            <div class="abf-field" style="margin-top:8px;">
                <label class="abf-label" for="admin-booking-repeat">{{ __('booking.admin.bookings.repeat') }}</label>
                <select name="repeat_type" id="admin-booking-repeat" class="abf-select">
                    @foreach($repeatOptions as $repeatValue => $repeatLabel)
                        <option value="{{ $repeatValue }}" @selected(old('repeat_type', $repeatType) === $repeatValue)>{{ $repeatLabel }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Card 3: Platz + Spieleranzahl + Spielernamen --}}
        <div class="abf-card">
            <div class="abf-field">
                <label class="abf-label" for="sid">{{ __('booking.admin.common.court') }}</label>
                <select name="sid" id="sid" class="abf-select">
                    @foreach($squares as $square)
                        <option value="{{ $square->sid }}" @selected(old('sid', $booking->sid) == $square->sid)>{{ $square->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="abf-field" style="margin-top:8px;">
                <label class="abf-label" for="admin-booking-quantity">{{ __('booking.admin.bookings.player_count') }}</label>
                <select name="quantity" id="admin-booking-quantity" class="abf-select">
                    <option value="2" @selected((int) old('quantity', $booking->quantity) === 2)>2</option>
                    <option value="4" @selected((int) old('quantity', $booking->quantity) === 4)>4</option>
                </select>
            </div>
            <div style="margin-top:10px;">
                <span class="abf-label">{{ __('booking.admin.bookings.player_names') }}</span>
                <div class="abf-field" style="margin-top:4px;">
                    <label class="abf-sublabel" for="admin-player2">2.</label>
                    <input type="text" id="admin-player2" name="player_name_2" value="{{ old('player_name_2', $playerNames[2]) }}" list="admin-player-suggestions" maxlength="120" class="abf-input">
                </div>
                <div class="abf-field" id="admin-player3-field">
                    <label class="abf-sublabel" for="admin-player3">3.</label>
                    <input type="text" id="admin-player3" name="player_name_3" value="{{ old('player_name_3', $playerNames[3]) }}" list="admin-player-suggestions" maxlength="120" class="abf-input">
                </div>
                <div class="abf-field" id="admin-player4-field">
                    <label class="abf-sublabel" for="admin-player4">4.</label>
                    <input type="text" id="admin-player4" name="player_name_4" value="{{ old('player_name_4', $playerNames[4]) }}" list="admin-player-suggestions" maxlength="120" class="abf-input">
                </div>
            </div>
        </div>

    </div>

    {{-- Notizen below --}}
    <div class="abf-card abf-card--notes">
        <div class="abf-row2">
            <div class="abf-field" style="flex:1;">
                <label class="abf-label" for="admin_note">{{ __('booking.admin.bookings.notes_section') }}</label>
                <textarea id="admin_note" name="admin_note" rows="3" class="abf-input abf-textarea">{{ old('admin_note', $adminNote) }}</textarea>
            </div>
            <div class="abf-hint abf-hint--side">{{ __('booking.admin.bookings.note_hint') }}</div>
        </div>
    </div>

    <datalist id="admin-player-suggestions">
        @foreach($users as $user)
            <option value="{{ $user->alias }}"></option>
        @endforeach
    </datalist>

    @else
    {{-- Standard vertical layout for edit mode --}}
    <div class="admin-form__section">
        <div class="admin-form__section-title">{{ __('booking.admin.bookings.booked_for') }}</div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="booked_for">{{ __('booking.admin.bookings.booked_for') }}</label>
            <div class="admin-form__field">
                <input type="text" id="booked_for" name="booked_for" value="{{ old('booked_for', $bookedFor) }}"
                       list="admin-player-suggestions" maxlength="120" required>
            </div>
        </div>
        @if($isMemberOwner && Route::has('admin.users.edit') && auth()->user()->can('admin.user'))
            <div class="admin-form__row">
                <div class="admin-form__label"></div>
                <div class="admin-form__field"><a href="{{ route('admin.users.edit', $bookingUser) }}">{{ __('booking.admin.bookings.edit_user') }}</a></div>
            </div>
        @endif
    </div>

    <div class="admin-form__section">
        <div class="admin-form__section-title">{{ __('booking.admin.bookings.time_section') }}</div>
        <div class="admin-form__row admin-form__field--flex">
            <div class="admin-form__inline-group">
                <label class="admin-form__inline-label" for="time_start">{{ __('booking.admin.bookings.time_start') }}</label>
                <input type="time" id="time_start" name="time_start" value="{{ old('time_start', $reservation ? substr((string) $reservation->time_start, 0, 5) : '') }}">
            </div>
            <div class="admin-form__inline-group">
                <label class="admin-form__inline-label" for="time_end">{{ __('booking.admin.bookings.time_end') }}</label>
                <input type="time" id="time_end" name="time_end" value="{{ old('time_end', $reservation ? substr((string) $reservation->time_end, 0, 5) : '') }}">
            </div>
            <div class="admin-form__inline-group">
                <label class="admin-form__inline-label" for="date">{{ __('booking.admin.bookings.date_start') }}</label>
                <input type="date" id="date" name="date" value="{{ old('date', $reservation?->date) }}">
            </div>
            <div class="admin-form__inline-group">
                <label class="admin-form__inline-label" for="admin-booking-date-end">{{ __('booking.admin.bookings.date_end') }}</label>
                <input type="date" id="admin-booking-date-end" name="date_end" value="{{ old('date_end', $repeatEndDate) }}">
            </div>
        </div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="admin-booking-repeat">{{ __('booking.admin.bookings.repeat') }}</label>
            <div class="admin-form__field">
                <select name="repeat_type" id="admin-booking-repeat">
                    @foreach($repeatOptions as $repeatValue => $repeatLabel)
                        <option value="{{ $repeatValue }}" @selected(old('repeat_type', $repeatType) === $repeatValue)>{{ $repeatLabel }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="admin-form__section">
        <div class="admin-form__section-title">{{ __('booking.admin.bookings.booking_section') }}</div>
        <div class="admin-form__row admin-form__field--flex">
            <div class="admin-form__inline-group">
                <label class="admin-form__inline-label" for="sid">{{ __('booking.admin.common.court') }}</label>
                <select name="sid" id="sid">
                    @foreach($squares as $square)
                        <option value="{{ $square->sid }}" @selected(old('sid', $booking->sid) == $square->sid)>{{ $square->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="admin-form__inline-group">
                <label class="admin-form__inline-label" for="admin-booking-quantity">{{ __('booking.admin.bookings.player_count') }}</label>
                <select name="quantity" id="admin-booking-quantity">
                    <option value="2" @selected((int) old('quantity', $booking->quantity) === 2)>2</option>
                    <option value="4" @selected((int) old('quantity', $booking->quantity) === 4)>4</option>
                </select>
            </div>
            <div class="admin-form__inline-group">
                <label class="admin-form__inline-label" for="status">{{ __('booking.admin.bookings.booking_status') }}</label>
                <select name="status" id="status">
                    <option value="single" @selected(old('status', $booking->status) === 'single')>{{ __('booking.admin.bookings.status_active') }}</option>
                    <option value="subscription" @selected(old('status', $booking->status) === 'subscription')>{{ __('booking.admin.bookings.status_series') }}</option>
                    <option value="cancelled" @selected(old('status', $booking->status) === 'cancelled')>{{ __('booking.admin.bookings.status_cancelled') }}</option>
                </select>
            </div>
        </div>
    </div>

    <div class="admin-form__section">
        <div class="admin-form__section-title">{{ __('booking.admin.bookings.player_names') }}</div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="admin-player2">2.</label>
            <div class="admin-form__field"><input type="text" id="admin-player2" name="player_name_2" value="{{ old('player_name_2', $playerNames[2]) }}" list="admin-player-suggestions" maxlength="120" required></div>
        </div>
        <div class="admin-form__row" id="admin-player3-field">
            <label class="admin-form__label" for="admin-player3">3.</label>
            <div class="admin-form__field"><input type="text" id="admin-player3" name="player_name_3" value="{{ old('player_name_3', $playerNames[3]) }}" list="admin-player-suggestions" maxlength="120"></div>
        </div>
        <div class="admin-form__row" id="admin-player4-field">
            <label class="admin-form__label" for="admin-player4">4.</label>
            <div class="admin-form__field"><input type="text" id="admin-player4" name="player_name_4" value="{{ old('player_name_4', $playerNames[4]) }}" list="admin-player-suggestions" maxlength="120"></div>
        </div>
        <datalist id="admin-player-suggestions">
            @foreach($users as $user)<option value="{{ $user->alias }}"></option>@endforeach
        </datalist>
    </div>

    <div class="admin-form__section">
        <div class="admin-form__section-title">{{ __('booking.admin.bookings.notes_section') }}</div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="admin_note">{{ __('booking.admin.bookings.notes_section') }}</label>
            <div class="admin-form__field">
                <textarea id="admin_note" name="admin_note" rows="6">{{ old('admin_note', $adminNote) }}</textarea>
                <div class="admin-form__hint">{{ __('booking.admin.bookings.note_hint') }}</div>
                <div class="admin-form__hint">{{ __('booking.admin.bookings.booking_created_at', ['date' => $createdLabel]) }}</div>
            </div>
        </div>
    </div>
    @endif

    {{-- Actions --}}
    <div class="admin-form__actions">
        <button type="submit" class="admin-btn-primary">{{ __('booking.admin.common.save') }}</button>
        <a href="{{ $closeRoute }}" class="default-button">{{ __('booking.admin.common.abort') }}</a>

        @unless($isCreateMode)
            @if($booking->status !== 'cancelled')
                <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" onsubmit="return confirm('{{ __('booking.admin.bookings.confirm_cancel') }}')">
                    @csrf
                    <button type="submit" class="default-button">{{ __('booking.admin.bookings.cancel_booking') }}</button>
                </form>
            @endif
            <form method="POST" action="{{ route('admin.bookings.destroy', $booking) }}" onsubmit="return confirm('{{ __('booking.admin.bookings.confirm_delete') }}')">
                @csrf
                @method('DELETE')
                <button type="submit" class="abmelden-button default-button">{{ __('booking.admin.common.delete') }}</button>
            </form>
        @endunless
    </div>
</form>

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
});
</script>
@endsection
