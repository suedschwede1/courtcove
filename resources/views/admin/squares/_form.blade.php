@csrf
<label>{{ __('booking.admin.squares.name') }} <input type="text" name="name" value="{{ old('name', $form['name']) }}"></label>
<label>{{ __('booking.admin.squares.display_name') }} <input type="text" name="alias" value="{{ old('alias', $form['alias']) }}"></label>
<label>{{ __('booking.admin.squares.status') }}
    <select name="status">
        @foreach(['enabled' => __('booking.admin.squares.status_enabled'), 'readonly' => __('booking.admin.squares.status_readonly'), 'disabled' => __('booking.admin.squares.status_disabled')] as $val => $lbl)
            <option value="{{ $val }}" @selected(old('status', $form['status']) === $val)>{{ $lbl }}</option>
        @endforeach
    </select>
</label>
<label>{{ __('booking.admin.squares.readonly_message') }}
    <input type="text" name="readonly_message" value="{{ old('readonly_message', $form['readonly_message']) }}">
</label>
<label>{{ __('booking.admin.squares.priority') }} <input type="number" step="any" name="priority" value="{{ old('priority', $form['priority']) }}"></label>
<label>{{ __('booking.admin.squares.capacity') }} <input type="number" min="0" name="capacity" value="{{ old('capacity', $form['capacity']) }}"></label>
<label>{{ __('booking.admin.squares.ask_names') }}
    <select name="capacity_ask_names">
        @php $askLabels = ['' => __('booking.admin.squares.ask_names_none'), 'optional-names' => __('booking.admin.squares.ask_names_optional'), 'optional-names-email' => __('booking.admin.squares.ask_names_optional_email'), 'optional-names-phone' => __('booking.admin.squares.ask_names_optional_phone'), 'optional-names-email-phone' => __('booking.admin.squares.ask_names_optional_email_phone'), 'required-names' => __('booking.admin.squares.ask_names_required'), 'required-names-email' => __('booking.admin.squares.ask_names_required_email'), 'required-names-phone' => __('booking.admin.squares.ask_names_required_phone'), 'required-names-email-phone' => __('booking.admin.squares.ask_names_required_email_phone')]; @endphp
        @foreach($askLabels as $val => $lbl)
            <option value="{{ $val }}" @selected(old('capacity_ask_names', $form['capacity_ask_names']) === $val)>{{ $lbl }}</option>
        @endforeach
    </select>
</label>
<label><input type="checkbox" name="capacity_heterogenic" value="1" @checked(old('capacity_heterogenic', $form['capacity_heterogenic']))> {{ __('booking.admin.squares.heterogenic') }}</label>
<label><input type="checkbox" name="allow_notes" value="1" @checked(old('allow_notes', $form['allow_notes']))> {{ __('booking.admin.squares.allow_notes') }}</label>
<label>{{ __('booking.admin.squares.name_visibility') }}
    <select name="name_visibility">
        @foreach(['none' => __('booking.admin.squares.visibility_none'), 'private' => __('booking.admin.squares.visibility_private'), 'public' => __('booking.admin.squares.visibility_public')] as $val => $lbl)
            <option value="{{ $val }}" @selected(old('name_visibility', $form['name_visibility']) === $val)>{{ $lbl }}</option>
        @endforeach
    </select>
</label>
<label>{{ __('booking.admin.squares.time_start') }} <input type="time" name="time_start" value="{{ old('time_start', $form['time_start']) }}"></label>
<label>{{ __('booking.admin.squares.time_end') }} <input type="time" name="time_end" value="{{ old('time_end', $form['time_end']) }}"></label>
<label>{{ __('booking.admin.squares.time_block') }} <input type="number" min="0" name="time_block" value="{{ old('time_block', $form['time_block']) }}"></label>
<label>{{ __('booking.admin.squares.time_block_bookable') }} <input type="number" min="0" name="time_block_bookable" value="{{ old('time_block_bookable', $form['time_block_bookable']) }}"></label>
<label><input type="checkbox" name="pseudo_time_block_bookable" value="1" @checked(old('pseudo_time_block_bookable', $form['pseudo_time_block_bookable']))> {{ __('booking.admin.squares.pseudo_time_block_bookable') }}</label>
<label>{{ __('booking.admin.squares.time_block_bookable_max') }} <input type="number" min="0" name="time_block_bookable_max" value="{{ old('time_block_bookable_max', $form['time_block_bookable_max']) }}"></label>
<label>{{ __('booking.admin.squares.min_range_book') }} <input type="number" min="0" name="min_range_book" value="{{ old('min_range_book', $form['min_range_book']) }}"></label>
<label>{{ __('booking.admin.squares.range_book') }} <input type="number" min="0" name="range_book" value="{{ old('range_book', $form['range_book']) }}"></label>
<label>{{ __('booking.admin.squares.max_active_bookings') }} <input type="number" min="0" name="max_active_bookings" value="{{ old('max_active_bookings', $form['max_active_bookings']) }}"></label>
<label>{{ __('booking.admin.squares.range_cancel') }} <input type="number" step="0.01" min="0" name="range_cancel" value="{{ old('range_cancel', $form['range_cancel']) }}"></label>
<label>{{ __('booking.admin.squares.label_free') }} <input type="text" name="label_free" value="{{ old('label_free', $form['label_free']) }}"></label>
