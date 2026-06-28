@csrf

<div class="admin-form__section">
    <div class="admin-form__section-title">{{ __('booking.admin.squares.section_general') }}</div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="sf-name">{{ __('booking.admin.squares.name') }}</label>
        <div class="admin-form__field"><input id="sf-name" type="text" name="name" value="{{ old('name', $form['name']) }}"></div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="sf-alias">{{ __('booking.admin.squares.display_name') }}</label>
        <div class="admin-form__field">
            <input id="sf-alias" type="text" name="alias" value="{{ old('alias', $form['alias']) }}">
            <p class="admin-form__note">Sichtbarer Name im Kalender (z. B. Court B). Leer lassen, um nur die Nummer anzuzeigen.</p>
        </div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="sf-status">{{ __('booking.admin.squares.status') }}</label>
        <div class="admin-form__field">
            <select id="sf-status" name="status">
                @foreach(['enabled' => __('booking.admin.squares.status_enabled'), 'readonly' => __('booking.admin.squares.status_readonly'), 'disabled' => __('booking.admin.squares.status_disabled')] as $val => $lbl)
                    <option value="{{ $val }}" @selected(old('status', $form['status']) === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="sf-readonly-msg">{{ __('booking.admin.squares.readonly_message') }}</label>
        <div class="admin-form__field">
            <input id="sf-readonly-msg" type="text" name="readonly_message" value="{{ old('readonly_message', $form['readonly_message']) }}">
            <p class="admin-form__note">Nachricht, die angezeigt wird, wenn der Platz gesperrt ist.</p>
        </div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="sf-priority">{{ __('booking.admin.squares.priority') }}</label>
        <div class="admin-form__field"><input id="sf-priority" type="number" step="any" name="priority" value="{{ old('priority', $form['priority']) }}"></div>
    </div>
</div>

<div class="admin-form__section">
    <div class="admin-form__section-title">{{ __('booking.admin.squares.section_booking') }}</div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="sf-capacity">{{ __('booking.admin.squares.capacity') }}</label>
        <div class="admin-form__field">
            <input id="sf-capacity" type="number" min="0" name="capacity" value="{{ old('capacity', $form['capacity']) }}">
            <p class="admin-form__note">Wieviele Spieler passen auf einen Platz?</p>
        </div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="sf-ask-names">{{ __('booking.admin.squares.ask_names') }}</label>
        <div class="admin-form__field">
            @php $askLabels = ['' => __('booking.admin.squares.ask_names_none'), 'optional-names' => __('booking.admin.squares.ask_names_optional'), 'optional-names-email' => __('booking.admin.squares.ask_names_optional_email'), 'optional-names-phone' => __('booking.admin.squares.ask_names_optional_phone'), 'optional-names-email-phone' => __('booking.admin.squares.ask_names_optional_email_phone'), 'required-names' => __('booking.admin.squares.ask_names_required'), 'required-names-email' => __('booking.admin.squares.ask_names_required_email'), 'required-names-phone' => __('booking.admin.squares.ask_names_required_phone'), 'required-names-email-phone' => __('booking.admin.squares.ask_names_required_email_phone')]; @endphp
            <select id="sf-ask-names" name="capacity_ask_names">
                @foreach($askLabels as $val => $lbl)
                    <option value="{{ $val }}" @selected(old('capacity_ask_names', $form['capacity_ask_names']) === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="admin-form__row">
        <span class="admin-form__label"></span>
        <div class="admin-form__field">
            <label><input type="checkbox" name="capacity_heterogenic" value="1" @checked(old('capacity_heterogenic', $form['capacity_heterogenic']))> {{ __('booking.admin.squares.heterogenic') }}</label>
        </div>
    </div>
    <div class="admin-form__row">
        <span class="admin-form__label"></span>
        <div class="admin-form__field">
            <label><input type="checkbox" name="allow_notes" value="1" @checked(old('allow_notes', $form['allow_notes']))> {{ __('booking.admin.squares.allow_notes') }}</label>
        </div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="sf-name-visibility">{{ __('booking.admin.squares.name_visibility') }}</label>
        <div class="admin-form__field">
            <select id="sf-name-visibility" name="name_visibility">
                @foreach(['none' => __('booking.admin.squares.visibility_none'), 'private' => __('booking.admin.squares.visibility_private'), 'public' => __('booking.admin.squares.visibility_public')] as $val => $lbl)
                    <option value="{{ $val }}" @selected(old('name_visibility', $form['name_visibility']) === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div class="admin-form__section">
    <div class="admin-form__section-title">{{ __('booking.admin.squares.section_times') }}</div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="sf-time-start">{{ __('booking.admin.squares.time_start') }}</label>
        <div class="admin-form__field"><input id="sf-time-start" type="time" name="time_start" value="{{ old('time_start', $form['time_start']) }}"></div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="sf-time-end">{{ __('booking.admin.squares.time_end') }}</label>
        <div class="admin-form__field"><input id="sf-time-end" type="time" name="time_end" value="{{ old('time_end', $form['time_end']) }}"></div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="sf-time-block">{{ __('booking.admin.squares.time_block') }}</label>
        <div class="admin-form__field"><input id="sf-time-block" type="number" min="0" name="time_block" value="{{ old('time_block', $form['time_block']) }}"></div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="sf-time-block-bookable">{{ __('booking.admin.squares.time_block_bookable') }}</label>
        <div class="admin-form__field"><input id="sf-time-block-bookable" type="number" min="0" name="time_block_bookable" value="{{ old('time_block_bookable', $form['time_block_bookable']) }}"></div>
    </div>
    <div class="admin-form__row">
        <span class="admin-form__label"></span>
        <div class="admin-form__field">
            <label><input type="checkbox" name="pseudo_time_block_bookable" value="1" @checked(old('pseudo_time_block_bookable', $form['pseudo_time_block_bookable']))> {{ __('booking.admin.squares.pseudo_time_block_bookable') }}</label>
        </div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="sf-time-block-max">{{ __('booking.admin.squares.time_block_bookable_max') }}</label>
        <div class="admin-form__field"><input id="sf-time-block-max" type="number" min="0" name="time_block_bookable_max" value="{{ old('time_block_bookable_max', $form['time_block_bookable_max']) }}"></div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="sf-min-range-book">{{ __('booking.admin.squares.min_range_book') }}</label>
        <div class="admin-form__field"><input id="sf-min-range-book" type="number" min="0" name="min_range_book" value="{{ old('min_range_book', $form['min_range_book']) }}"></div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="sf-range-book">{{ __('booking.admin.squares.range_book') }}</label>
        <div class="admin-form__field">
            <input id="sf-range-book" type="number" min="0" name="range_book" value="{{ old('range_book', $form['range_book']) }}">
            <p class="admin-form__note">Wie viele Tage im Voraus kann max. gebucht werden?</p>
        </div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="sf-max-bookings">{{ __('booking.admin.squares.max_active_bookings') }}</label>
        <div class="admin-form__field">
            <input id="sf-max-bookings" type="number" min="0" name="max_active_bookings" value="{{ old('max_active_bookings', $form['max_active_bookings']) }}">
            <p class="admin-form__note">Auf 0 setzen, um beliebig viele Buchungen zu erlauben.</p>
        </div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="sf-range-cancel">{{ __('booking.admin.squares.range_cancel') }}</label>
        <div class="admin-form__field">
            <input id="sf-range-cancel" type="number" step="0.01" min="0" name="range_cancel" value="{{ old('range_cancel', $form['range_cancel']) }}">
            <p class="admin-form__note">Bis wann darf spätestens storniert werden? 0 = nie stornieren, 0,01 = praktisch immer.</p>
        </div>
    </div>
</div>

<div class="admin-form__section">
    <div class="admin-form__section-title">{{ __('booking.admin.squares.section_display') }}</div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="sf-label-free">{{ __('booking.admin.squares.label_free') }}</label>
        <div class="admin-form__field">
            <input id="sf-label-free" type="text" name="label_free" value="{{ old('label_free', $form['label_free']) }}">
            <p class="admin-form__note">Individuelle Bezeichnung freier Plätze; Standard ist „Frei".</p>
        </div>
    </div>
</div>
