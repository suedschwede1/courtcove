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

<div class="admin-booking-shell">
    <a href="{{ $closeRoute }}" class="admin-booking-close" aria-label="{{ __('booking.admin.common.close') }}">&times;</a>

    <form method="POST" action="{{ $formAction }}" class="admin-booking-form" id="{{ $formId }}">
        @csrf
        @unless($isCreateMode)
            @method('PUT')
        @endunless

        <div class="admin-booking-layout">
            <section class="admin-booking-card admin-booking-card--user">
                <h2 class="admin-booking-card__title">{{ __('booking.admin.bookings.booked_for') }}</h2>
                <label class="admin-booking-field admin-booking-field--full">
                    <input type="text" name="booked_for" value="{{ old('booked_for', $bookedFor) }}"
                           list="admin-player-suggestions" maxlength="120" class="admin-booking-input" required>
                </label>
                @if($isMemberOwner)
                    <div class="admin-booking-user-name">{{ $memberLabel }}</div>
                    <div class="admin-booking-user-meta">
                        <div>{{ __('booking.admin.common.status') }}: {{ $userStatusLabel }}</div>
                        <div>{{ __('booking.admin.bookings.email_label') }}: {{ $userEmail }}</div>
                        @if($userPhone)
                            <div>{{ __('booking.admin.bookings.phone_label') }}: {{ $userPhone }}</div>
                        @endif
                    </div>
                    @if(Route::has('admin.users.edit') && auth()->user()->can('admin.user'))
                        <a href="{{ route('admin.users.edit', $bookingUser) }}" class="admin-booking-link">{{ __('booking.admin.bookings.edit_user') }}</a>
                    @endif
                @endif
            </section>

            <section class="admin-booking-card">
                <h2 class="admin-booking-card__title">{{ __('booking.admin.bookings.time_section') }}</h2>
                <div class="admin-booking-grid admin-booking-grid--two">
                    <label class="admin-booking-field">
                        <span class="admin-booking-field__label">{{ __('booking.admin.bookings.time_start') }}</span>
                        <input type="time" name="time_start" value="{{ old('time_start', $reservation ? substr((string) $reservation->time_start, 0, 5) : '') }}" class="admin-booking-input">
                    </label>
                    <label class="admin-booking-field">
                        <span class="admin-booking-field__label">{{ __('booking.admin.bookings.time_end') }}</span>
                        <input type="time" name="time_end" value="{{ old('time_end', $reservation ? substr((string) $reservation->time_end, 0, 5) : '') }}" class="admin-booking-input">
                    </label>
                    <label class="admin-booking-field">
                        <span class="admin-booking-field__label">{{ __('booking.admin.bookings.date_start') }}</span>
                        <input type="date" name="date" value="{{ old('date', $reservation?->date) }}" class="admin-booking-input">
                    </label>
                    <label class="admin-booking-field">
                        <span class="admin-booking-field__label">{{ __('booking.admin.bookings.date_end') }}</span>
                        <input type="date" name="date_end" id="admin-booking-date-end" value="{{ old('date_end', $repeatEndDate) }}" class="admin-booking-input">
                    </label>
                    <label class="admin-booking-field admin-booking-field--full admin-booking-field--repeat">
                        <span class="admin-booking-field__label">{{ __('booking.admin.bookings.repeat') }}</span>
                        <select name="repeat_type" id="admin-booking-repeat" class="admin-booking-select">
                            @foreach($repeatOptions as $repeatValue => $repeatLabel)
                                <option value="{{ $repeatValue }}" @selected(old('repeat_type', $repeatType) === $repeatValue)>{{ $repeatLabel }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </section>

            <section class="admin-booking-card">
                <h2 class="admin-booking-card__title">{{ __('booking.admin.bookings.booking_section') }}</h2>
                <div class="admin-booking-grid admin-booking-grid--stack">
                    <label class="admin-booking-field">
                        <span class="admin-booking-field__label">{{ __('booking.admin.common.court') }}</span>
                        <select name="sid" class="admin-booking-select">
                            @foreach($squares as $square)
                                <option value="{{ $square->sid }}" @selected(old('sid', $booking->sid) == $square->sid)>{{ $square->display_name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="admin-booking-field">
                        <span class="admin-booking-field__label">{{ __('booking.admin.bookings.player_count') }}</span>
                        <select name="quantity" id="admin-booking-quantity" class="admin-booking-select">
                            <option value="2" @selected((int) old('quantity', $booking->quantity) === 2)>2</option>
                            <option value="4" @selected((int) old('quantity', $booking->quantity) === 4)>4</option>
                        </select>
                    </label>

                    <label class="admin-booking-field">
                        <span class="admin-booking-field__label">{{ __('booking.admin.bookings.booking_status') }}</span>
                        <select name="status" class="admin-booking-select">
                            <option value="single" @selected(old('status', $booking->status) === 'single')>{{ __('booking.admin.bookings.status_active') }}</option>
                            <option value="subscription" @selected(old('status', $booking->status) === 'subscription')>{{ __('booking.admin.bookings.status_series') }}</option>
                            <option value="cancelled" @selected(old('status', $booking->status) === 'cancelled')>{{ __('booking.admin.bookings.status_cancelled') }}</option>
                        </select>
                    </label>
                </div>

                <div class="admin-booking-players">
                    <div class="admin-booking-field__label">{{ __('booking.admin.bookings.player_names') }}</div>
                    <label class="admin-booking-field admin-booking-field--player" id="admin-player2-field">
                        <span class="admin-booking-player-index">2.</span>
                        <input type="text" id="admin-player2" name="player_name_2" value="{{ old('player_name_2', $playerNames[2]) }}" list="admin-player-suggestions" maxlength="120" class="admin-booking-input" required>
                    </label>
                    <label class="admin-booking-field admin-booking-field--player" id="admin-player3-field">
                        <span class="admin-booking-player-index">3.</span>
                        <input type="text" id="admin-player3" name="player_name_3" value="{{ old('player_name_3', $playerNames[3]) }}" list="admin-player-suggestions" maxlength="120" class="admin-booking-input">
                    </label>
                    <label class="admin-booking-field admin-booking-field--player" id="admin-player4-field">
                        <span class="admin-booking-player-index">4.</span>
                        <input type="text" id="admin-player4" name="player_name_4" value="{{ old('player_name_4', $playerNames[4]) }}" list="admin-player-suggestions" maxlength="120" class="admin-booking-input">
                    </label>
                </div>
                <datalist id="admin-player-suggestions">
                    @foreach($users as $user)
                        <option value="{{ $user->alias }}"></option>
                    @endforeach
                </datalist>
            </section>

            <section class="admin-booking-card admin-booking-card--notes">
                <h2 class="admin-booking-card__title">{{ __('booking.admin.bookings.notes_section') }}</h2>
                <label class="admin-booking-field admin-booking-field--full">
                    <textarea name="admin_note" rows="6" class="admin-booking-textarea">{{ old('admin_note', $adminNote) }}</textarea>
                </label>
                <div class="admin-booking-note-hint">{{ __('booking.admin.bookings.note_hint') }}</div>
                <div class="admin-booking-created">{{ __('booking.admin.bookings.booking_created_at', ['date' => $createdLabel]) }}</div>
            </section>
        </div>
    </form>

    <div class="admin-booking-actions">
        <button type="submit" form="{{ $formId }}" class="default-button admin-booking-actions__save">{{ __('booking.admin.common.save') }}</button>
        <span class="admin-booking-actions__divider">{{ __('booking.admin.common.or') }}</span>
        <a href="{{ $closeRoute }}" class="default-button admin-booking-actions__cancel">{{ __('booking.admin.common.abort') }}</a>
        @unless($isCreateMode)
            <form method="POST" action="{{ route('admin.bookings.destroy', $booking) }}" onsubmit="return confirm('{{ __('booking.admin.bookings.confirm_delete') }}')">
                @csrf
                @method('DELETE')
                <button type="submit" class="abmelden-button default-button">{{ __('booking.admin.common.delete') }}</button>
            </form>
        @endunless
    </div>

    @unless($isCreateMode)
        @if($booking->status !== 'cancelled')
            <div class="admin-booking-secondary-actions">
                <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" onsubmit="return confirm('{{ __('booking.admin.bookings.confirm_cancel') }}')">
                    @csrf
                    <button type="submit" class="admin-booking-link-button">{{ __('booking.admin.bookings.cancel_booking') }}</button>
                </form>
            </div>
        @endif
    @endunless
</div>

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
