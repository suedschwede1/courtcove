@csrf
<p class="admin-form__lang-hint">
    <span class="admin-form__lang-hint-icon">ℹ</span>
    {{ __('booking.admin.events.lang_hint') }}
</p>

<div class="admin-form__section">
    <div class="admin-form__row">
        <label class="admin-form__label" for="ef-name">{{ __('booking.admin.events.name') }}</label>
        <div class="admin-form__field">
            <input id="ef-name" type="text" name="name" value="{{ old('name', $name ?? '') }}">
        </div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="ef-description">{{ __('booking.admin.events.description') }}</label>
        <div class="admin-form__field">
            <textarea id="ef-description" name="description" rows="6">{{ old('description', $description ?? '') }}</textarea>
        </div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label">{{ __('booking.admin.events.date_start') }}</label>
        <div class="admin-form__field admin-form__field--flex">
            <div class="admin-form__inline-group">
                <span class="admin-form__inline-label">{{ __('booking.admin.events.date_start') }}</span>
                <input type="date" name="date_start" value="{{ old('date_start', $date_start ?? '') }}">
            </div>
            <div class="admin-form__inline-group">
                <span class="admin-form__inline-label">{{ __('booking.admin.events.time_start') }}</span>
                <input type="time" name="time_start" value="{{ old('time_start', $time_start ?? '') }}">
            </div>
        </div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label">{{ __('booking.admin.events.date_end') }}</label>
        <div class="admin-form__field admin-form__field--flex">
            <div class="admin-form__inline-group">
                <span class="admin-form__inline-label">{{ __('booking.admin.events.date_end') }}</span>
                <input type="date" name="date_end" value="{{ old('date_end', $date_end ?? '') }}">
            </div>
            <div class="admin-form__inline-group">
                <span class="admin-form__inline-label">{{ __('booking.admin.events.time_end') }}</span>
                <input type="time" name="time_end" value="{{ old('time_end', $time_end ?? '') }}">
            </div>
        </div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label">{{ __('booking.admin.events.court') }}</label>
        <div class="admin-form__field admin-form__field--flex">
            <div class="admin-form__inline-group">
                <span class="admin-form__inline-label">{{ __('booking.admin.events.court') }}</span>
                <select name="sid">
                    <option value="">{{ __('booking.admin.events.all_courts') }}</option>
                    @foreach($squares as $s)
                        <option value="{{ $s->sid }}" @selected((string) old('sid', $event->sid ?? '') === (string) $s->sid)>{{ $s->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="admin-form__inline-group">
                <span class="admin-form__inline-label">{{ __('booking.admin.events.capacity') }}</span>
                <input type="number" name="capacity" value="{{ old('capacity', $event->capacity ?? 0) }}" min="0" style="width:100px">
                <span class="admin-form__note">{{ __('booking.admin.events.capacity_hint') }}</span>
            </div>
        </div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="ef-notes">{{ __('booking.admin.events.notes') }}</label>
        <div class="admin-form__field">
            <textarea id="ef-notes" name="notes" rows="3">{{ old('notes', $notes ?? '') }}</textarea>
            <p class="admin-form__note">{{ __('booking.admin.events.notes_hint') }}</p>
        </div>
    </div>
</div>
