@csrf
<label>Name <input type="text" name="name" value="{{ old('name', $form['name']) }}"></label>
<label>Anzeigename <input type="text" name="alias" value="{{ old('alias', $form['alias']) }}"></label>
<label>Status
    <select name="status">
        @foreach(['enabled' => 'Aktiviert', 'readonly' => 'Nur Verwaltung', 'disabled' => 'Deaktiviert'] as $val => $lbl)
            <option value="{{ $val }}" @selected(old('status', $form['status']) === $val)>{{ $lbl }}</option>
        @endforeach
    </select>
</label>
<label>Nachricht (bei „Nur Verwaltung")
    <input type="text" name="readonly_message" value="{{ old('readonly_message', $form['readonly_message']) }}">
</label>
<label>Priorität <input type="number" step="any" name="priority" value="{{ old('priority', $form['priority']) }}"></label>
<label>Kapazität <input type="number" min="0" name="capacity" value="{{ old('capacity', $form['capacity']) }}"></label>
<label>Namen anderer Spieler
    <select name="capacity_ask_names">
        @php $askLabels = ['' => 'Nicht fragen', 'optional-names' => 'Namen (optional)', 'optional-names-email' => 'Namen + E-Mail (optional)', 'optional-names-phone' => 'Namen + Telefon (optional)', 'optional-names-email-phone' => 'Namen + E-Mail + Telefon (optional)', 'required-names' => 'Namen (Pflicht)', 'required-names-email' => 'Namen + E-Mail (Pflicht)', 'required-names-phone' => 'Namen + Telefon (Pflicht)', 'required-names-email-phone' => 'Namen + E-Mail + Telefon (Pflicht)']; @endphp
        @foreach($askLabels as $val => $lbl)
            <option value="{{ $val }}" @selected(old('capacity_ask_names', $form['capacity_ask_names']) === $val)>{{ $lbl }}</option>
        @endforeach
    </select>
</label>
<label><input type="checkbox" name="capacity_heterogenic" value="1" @checked(old('capacity_heterogenic', $form['capacity_heterogenic']))> Mehrfachbuchungen</label>
<label><input type="checkbox" name="allow_notes" value="1" @checked(old('allow_notes', $form['allow_notes']))> Anmerkungen bei der Buchung erlauben</label>
<label>Sichtbarkeit von Namen
    <select name="name_visibility">
        @foreach(['none' => 'Niemand', 'private' => 'Angemeldete Benutzer', 'public' => 'Alle'] as $val => $lbl)
            <option value="{{ $val }}" @selected(old('name_visibility', $form['name_visibility']) === $val)>{{ $lbl }}</option>
        @endforeach
    </select>
</label>
<label>Zeit (Beginn) <input type="time" name="time_start" value="{{ old('time_start', $form['time_start']) }}"></label>
<label>Zeit (Ende) <input type="time" name="time_end" value="{{ old('time_end', $form['time_end']) }}"></label>
<label>Zeitblock (Minuten) <input type="number" min="0" name="time_block" value="{{ old('time_block', $form['time_block']) }}"></label>
<label>Zeitblock min. buchbar (Minuten) <input type="number" min="0" name="time_block_bookable" value="{{ old('time_block_bookable', $form['time_block_bookable']) }}"></label>
<label><input type="checkbox" name="pseudo_time_block_bookable" value="1" @checked(old('pseudo_time_block_bookable', $form['pseudo_time_block_bookable']))> Min. buchbaren Zeitblock nur für die Verwaltung</label>
<label>Zeitblock max. buchbar (Minuten) <input type="number" min="0" name="time_block_bookable_max" value="{{ old('time_block_bookable_max', $form['time_block_bookable_max']) }}"></label>
<label>Buchungsvorlauf (Minuten) <input type="number" min="0" name="min_range_book" value="{{ old('min_range_book', $form['min_range_book']) }}"></label>
<label>Buchung im Voraus (Tage) <input type="number" min="0" name="range_book" value="{{ old('range_book', $form['range_book']) }}"></label>
<label>Buchungen einschränken (gleichzeitig pro Benutzer, 0 = unbegrenzt) <input type="number" min="0" name="max_active_bookings" value="{{ old('max_active_bookings', $form['max_active_bookings']) }}"></label>
<label>Stornierung (Stunden) <input type="number" step="0.01" min="0" name="range_cancel" value="{{ old('range_cancel', $form['range_cancel']) }}"></label>
<label>Bezeichnung freier Plätze <input type="text" name="label_free" value="{{ old('label_free', $form['label_free']) }}"></label>
