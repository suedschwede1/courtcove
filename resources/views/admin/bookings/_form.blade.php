<label>{{ __('booking.admin.common.member') }}
    <select name="uid">
        @foreach($users as $user)
            <option value="{{ $user->uid }}" @selected(old('uid', $booking->uid) == $user->uid)>{{ $user->alias }}</option>
        @endforeach
    </select>
</label>

<label>{{ __('booking.admin.common.court') }}
    <select name="sid">
        @foreach($squares as $square)
            <option value="{{ $square->sid }}" @selected(old('sid', $booking->sid) == $square->sid)>{{ $square->display_name }}</option>
        @endforeach
    </select>
</label>

<label>{{ __('booking.admin.common.date') }}
    <input type="date" name="date" value="{{ old('date', $reservation?->date) }}">
</label>

<label>{{ __('booking.admin.common.from') }}
    <input type="time" name="time_start" value="{{ old('time_start', $reservation ? substr((string) $reservation->time_start, 0, 5) : '') }}">
</label>

<label>{{ __('booking.admin.common.to') }}
    <input type="time" name="time_end" value="{{ old('time_end', $reservation ? substr((string) $reservation->time_end, 0, 5) : '') }}">
</label>

<label>{{ __('booking.admin.bookings.play_type') }}
    <select name="quantity" id="admin-booking-quantity">
        <option value="2" @selected((int) old('quantity', $booking->quantity) === 2)>{{ __('booking.admin.bookings.single') }}</option>
        <option value="4" @selected((int) old('quantity', $booking->quantity) === 4)>{{ __('booking.admin.bookings.double') }}</option>
    </select>
</label>

<label>{{ __('booking.admin.common.status') }}
    <select name="status">
        <option value="single" @selected(old('status', $booking->status) === 'single')>{{ __('booking.admin.bookings.status_active') }}</option>
        <option value="cancelled" @selected(old('status', $booking->status) === 'cancelled')>{{ __('booking.admin.bookings.status_cancelled') }}</option>
    </select>
</label>

<label id="admin-player2-field">{{ __('booking.admin.bookings.additional_player') }}
    <input type="text" id="admin-player2" name="player_name_2" value="{{ old('player_name_2', $playerNames[2]) }}" list="admin-player-suggestions" maxlength="120" required>
</label>

<label id="admin-player3-field">{{ __('booking.admin.bookings.player_name_3') }}
    <input type="text" id="admin-player3" name="player_name_3" value="{{ old('player_name_3', $playerNames[3]) }}" list="admin-player-suggestions" maxlength="120">
</label>

<label id="admin-player4-field">{{ __('booking.admin.bookings.player_name_4') }}
    <input type="text" id="admin-player4" name="player_name_4" value="{{ old('player_name_4', $playerNames[4]) }}" list="admin-player-suggestions" maxlength="120">
</label>

<datalist id="admin-player-suggestions">
    @foreach($users as $user)
        <option value="{{ $user->alias }}"></option>
    @endforeach
</datalist>
