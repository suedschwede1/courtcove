<div class="admin-form__section">
    <h3 class="admin-form__section-title">{{ __('booking.admin.common.booking') ?? 'Buchung' }}</h3>

    <div class="admin-form__row">
        <label class="admin-form__label">{{ __('booking.admin.common.member') }}</label>
        <div class="admin-form__field">
            <select name="uid">
                @foreach($users as $user)
                    <option value="{{ $user->uid }}" @selected(old('uid', $booking->uid) == $user->uid)>{{ $user->alias }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="admin-form__row">
        <label class="admin-form__label">{{ __('booking.admin.common.court') }}</label>
        <div class="admin-form__field">
            <select name="sid">
                @foreach($squares as $square)
                    <option value="{{ $square->sid }}" @selected(old('sid', $booking->sid) == $square->sid)>{{ $square->display_name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="admin-form__row">
        <label class="admin-form__label">{{ __('booking.admin.common.date') }}</label>
        <div class="admin-form__field">
            <input type="date" name="date" value="{{ old('date', $reservation?->date) }}">
        </div>
    </div>

    <div class="admin-form__row">
        <label class="admin-form__label">{{ __('booking.admin.common.from') }}</label>
        <div class="admin-form__field">
            <input type="time" name="time_start" value="{{ old('time_start', $reservation ? substr((string) $reservation->time_start, 0, 5) : '') }}">
        </div>
    </div>

    <div class="admin-form__row">
        <label class="admin-form__label">{{ __('booking.admin.common.to') }}</label>
        <div class="admin-form__field">
            <input type="time" name="time_end" value="{{ old('time_end', $reservation ? substr((string) $reservation->time_end, 0, 5) : '') }}">
        </div>
    </div>

    <div class="admin-form__row">
        <label class="admin-form__label">{{ __('booking.admin.bookings.play_type') }}</label>
        <div class="admin-form__field">
            <select name="quantity" id="admin-booking-quantity">
                <option value="2" @selected((int) old('quantity', $booking->quantity) === 2)>{{ __('booking.admin.bookings.single') }}</option>
                <option value="4" @selected((int) old('quantity', $booking->quantity) === 4)>{{ __('booking.admin.bookings.double') }}</option>
            </select>
        </div>
    </div>

    <div class="admin-form__row">
        <label class="admin-form__label">{{ __('booking.admin.common.status') }}</label>
        <div class="admin-form__field">
            <select name="status">
                <option value="single" @selected(old('status', $booking->status) === 'single')>{{ __('booking.admin.bookings.status_active') }}</option>
                <option value="cancelled" @selected(old('status', $booking->status) === 'cancelled')>{{ __('booking.admin.bookings.status_cancelled') }}</option>
            </select>
        </div>
    </div>
</div>

<div class="admin-form__section">
    <h3 class="admin-form__section-title">{{ __('booking.admin.bookings.player_names') ?? 'Spielernamen' }}</h3>

    <div class="admin-form__row">
        <label class="admin-form__label" for="admin-player2">{{ __('booking.admin.bookings.additional_player') }}</label>
        <div class="admin-form__field">
            <input type="text" id="admin-player2" name="player_name_2" value="{{ old('player_name_2', $playerNames[2]) }}" list="admin-player-suggestions" maxlength="120" required>
        </div>
    </div>

    <div class="admin-form__row">
        <label class="admin-form__label" for="admin-player3">{{ __('booking.admin.bookings.player_name_3') }}</label>
        <div class="admin-form__field">
            <input type="text" id="admin-player3" name="player_name_3" value="{{ old('player_name_3', $playerNames[3]) }}" list="admin-player-suggestions" maxlength="120">
        </div>
    </div>

    <div class="admin-form__row">
        <label class="admin-form__label" for="admin-player4">{{ __('booking.admin.bookings.player_name_4') }}</label>
        <div class="admin-form__field">
            <input type="text" id="admin-player4" name="player_name_4" value="{{ old('player_name_4', $playerNames[4]) }}" list="admin-player-suggestions" maxlength="120">
        </div>
    </div>
</div>

<datalist id="admin-player-suggestions">
    @foreach($users as $user)
        <option value="{{ $user->alias }}"></option>
    @endforeach
</datalist>
