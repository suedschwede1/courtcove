<div class="admin-form__section">
    <div class="admin-form__section-title">{{ __('booking.admin.events.section_event') }}</div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="ef-sid">{{ __('booking.admin.events.court') }}</label>
        <div class="admin-form__field">
            <select id="ef-sid" name="sid">
                <option value="">{{ __('booking.admin.events.all_courts') }}</option>
                @foreach($squares as $s)
                    <option value="{{ $s->sid }}" @selected((string) old('sid', $event->sid ?? '') === (string) $s->sid)>{{ $s->display_name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="ef-name">{{ __('booking.admin.events.name') }}</label>
        <div class="admin-form__field"><input id="ef-name" type="text" name="name" value="{{ old('name', $name ?? '') }}"></div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="ef-status">{{ __('booking.admin.events.status') }}</label>
        <div class="admin-form__field">
            <select id="ef-status" name="status">
                @foreach(['enabled', 'disabled'] as $st)
                    <option value="{{ $st }}" @selected(old('status', $event->status ?? 'enabled') === $st)>{{ $st }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div class="admin-form__section">
    <div class="admin-form__section-title">{{ __('booking.admin.events.section_period') }}</div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="ef-start">{{ __('booking.admin.events.from') }}</label>
        <div class="admin-form__field">
            <input id="ef-start" type="datetime-local" name="datetime_start"
                value="{{ ($v = old('datetime_start', $event->datetime_start ?? '')) ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d\TH:i') : '' }}">
        </div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="ef-end">{{ __('booking.admin.events.to') }}</label>
        <div class="admin-form__field">
            <input id="ef-end" type="datetime-local" name="datetime_end"
                value="{{ ($v = old('datetime_end', $event->datetime_end ?? '')) ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d\TH:i') : '' }}">
        </div>
    </div>
</div>
