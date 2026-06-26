<label>Mitglied
    <select name="uid">
        @foreach($users as $user)
            <option value="{{ $user->uid }}" @selected(old('uid', $booking->uid) == $user->uid)>{{ $user->alias }}</option>
        @endforeach
    </select>
</label>

<label>Platz
    <select name="sid">
        @foreach($squares as $square)
            <option value="{{ $square->sid }}" @selected(old('sid', $booking->sid) == $square->sid)>{{ $square->display_name }}</option>
        @endforeach
    </select>
</label>

<label>Datum
    <input type="date" name="date" value="{{ old('date', $reservation?->date) }}">
</label>

<label>Von
    <input type="time" name="time_start" value="{{ old('time_start', $reservation ? substr((string) $reservation->time_start, 0, 5) : '') }}">
</label>

<label>Bis
    <input type="time" name="time_end" value="{{ old('time_end', $reservation ? substr((string) $reservation->time_end, 0, 5) : '') }}">
</label>

<label>Spielart
    <select name="quantity" id="admin-booking-quantity">
        <option value="2" @selected((int) old('quantity', $booking->quantity) === 2)>Einzel</option>
        <option value="4" @selected((int) old('quantity', $booking->quantity) === 4)>Doppel</option>
    </select>
</label>

<label>Status
    <select name="status">
        <option value="single" @selected(old('status', $booking->status) === 'single')>Aktiv</option>
        <option value="cancelled" @selected(old('status', $booking->status) === 'cancelled')>Storniert</option>
    </select>
</label>

<label id="admin-player2-field">Zusätzlicher Spieler
    <input type="text" id="admin-player2" name="player_name_2" value="{{ old('player_name_2', $playerNames[2]) }}" list="admin-player-suggestions" maxlength="120" required>
</label>

<label id="admin-player3-field">3. Spielername
    <input type="text" id="admin-player3" name="player_name_3" value="{{ old('player_name_3', $playerNames[3]) }}" list="admin-player-suggestions" maxlength="120">
</label>

<label id="admin-player4-field">4. Spielername
    <input type="text" id="admin-player4" name="player_name_4" value="{{ old('player_name_4', $playerNames[4]) }}" list="admin-player-suggestions" maxlength="120">
</label>

<datalist id="admin-player-suggestions">
    @foreach($users as $user)
        <option value="{{ $user->alias }}"></option>
    @endforeach
</datalist>
