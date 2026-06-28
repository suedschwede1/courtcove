@csrf
<label>{{ __('booking.admin.events.court') }}
    <select name="sid">
        <option value="">{{ __('booking.admin.events.all_courts') }}</option>
        @foreach($squares as $s)
            <option value="{{ $s->sid }}" @selected((string) old('sid', $event->sid ?? '') === (string) $s->sid)>{{ $s->display_name }}</option>
        @endforeach
    </select>
</label>
<label>{{ __('booking.admin.events.name') }} <input type="text" name="name" value="{{ old('name', $name ?? '') }}"></label>
<label>{{ __('booking.admin.events.status') }}
    <select name="status">
        @foreach(['enabled', 'disabled'] as $st)
            <option value="{{ $st }}" @selected(old('status', $event->status ?? 'enabled') === $st)>{{ $st }}</option>
        @endforeach
    </select>
</label>
<label>{{ __('booking.admin.events.from') }} <input type="datetime-local" name="datetime_start"
    value="{{ ($v = old('datetime_start', $event->datetime_start ?? '')) ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d\TH:i') : '' }}"></label>
<label>{{ __('booking.admin.events.to') }} <input type="datetime-local" name="datetime_end"
    value="{{ ($v = old('datetime_end', $event->datetime_end ?? '')) ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d\TH:i') : '' }}"></label>
