@csrf
@if(!empty($popup_mode))
    <div class="space-y-4">
        <div class="space-y-1">
            <label class="block text-[13px] font-medium text-[#151515]" for="ef-name">{{ __('booking.admin.events.name') }}</label>
            <input id="ef-name" type="text" name="name" value="{{ old('name', $name ?? '') }}" required placeholder="{{ __('booking.admin.events.name_placeholder') }}" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none placeholder:text-[#b8b8b8] focus:border-[#151515]">
        </div>

        <div class="space-y-1">
            <label class="block text-[13px] font-medium text-[#151515]" for="ef-description">{{ __('booking.admin.events.description') }}</label>
            <textarea id="ef-description" name="description" rows="4" class="w-full rounded-[6px] border border-[#c7c7c7] bg-white px-3 py-2 text-sm text-[#151515] outline-none focus:border-[#151515]">{{ old('description', $description ?? '') }}</textarea>
        </div>

        <div>
            <div class="grid grid-cols-4 gap-3">
                <div class="space-y-1">
                    <label class="block text-[13px] font-medium text-[#151515]">{{ __('booking.admin.events.date_start') }}</label>
                    <input type="date" name="date_start" value="{{ old('date_start', $date_start ?? '') }}" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                </div>
                <div class="space-y-1">
                    <label class="block text-[13px] font-medium text-[#151515]">{{ __('booking.admin.events.time_start') }}</label>
                    <input type="time" name="time_start" value="{{ old('time_start', $time_start ?? '') }}" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                </div>
                <div class="space-y-1">
                    <label class="block text-[13px] font-medium text-[#151515]">{{ __('booking.admin.events.date_end') }}</label>
                    <input type="date" name="date_end" value="{{ old('date_end', $date_end ?? '') }}" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                </div>
                <div class="space-y-1">
                    <label class="block text-[13px] font-medium text-[#151515]">{{ __('booking.admin.events.time_end') }}</label>
                    <input type="time" name="time_end" value="{{ old('time_end', $time_end ?? '') }}" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                </div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="block text-[13px] font-medium text-[#151515]">{{ __('booking.admin.events.court') }}</label>
                    <select name="sid" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                        <option value="">{{ __('booking.admin.events.all_courts') }}</option>
                        @foreach($squares as $s)
                            <option value="{{ $s->sid }}" @selected((string) old('sid', $event->sid ?? '') === (string) $s->sid)>{{ $s->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="block text-[13px] font-medium text-[#151515]">{{ __('booking.admin.events.capacity') }}</label>
                    <input type="number" name="capacity" value="{{ old('capacity', $event->capacity ?? 0) }}" min="0" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                    <p class="text-xs text-[#6a6e73]">{{ __('booking.admin.events.capacity_zero_hint') }}</p>
                </div>
            </div>
        </div>

        <div class="space-y-1">
            <label class="block text-[13px] font-medium text-[#151515]" for="ef-notes">{{ __('booking.admin.events.notes') }}</label>
            <textarea id="ef-notes" name="notes" rows="4" class="w-full rounded-[6px] border border-[#c7c7c7] bg-white px-3 py-2 text-sm text-[#151515] outline-none focus:border-[#151515]">{{ old('notes', $notes ?? '') }}</textarea>
            <p class="text-xs text-[#6a6e73]">{{ __('booking.admin.events.notes_hint') }}</p>
        </div>
    </div>
@else
<div class="ui-form-shell">
    <div class="ui-field">
        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="ef-name">{{ __('booking.admin.events.name') }}</label>
        <input id="ef-name" type="text" name="name" value="{{ old('name', $name ?? '') }}" required class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
    </div>

    <div class="ui-field">
        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="ef-description">{{ __('booking.admin.events.description') }}</label>
        <textarea id="ef-description" name="description" rows="4" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">{{ old('description', $description ?? '') }}</textarea>
    </div>

    <div class="ui-form-divider">
        <p class="ui-section-label !mb-0">{{ __('booking.admin.events.period') }}</p>
        <div class="ui-grid-4">
        <div class="ui-field">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.events.date_start') }}</label>
            <input type="date" name="date_start" value="{{ old('date_start', $date_start ?? '') }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
        <div class="ui-field">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.events.time_start') }}</label>
            <input type="time" name="time_start" value="{{ old('time_start', $time_start ?? '') }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
        <div class="ui-field">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.events.date_end') }}</label>
            <input type="date" name="date_end" value="{{ old('date_end', $date_end ?? '') }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
        <div class="ui-field">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.events.time_end') }}</label>
            <input type="time" name="time_end" value="{{ old('time_end', $time_end ?? '') }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
        </div>
    </div>

    <div class="ui-grid-2">
        <div class="ui-field">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.events.court') }}</label>
            <select name="sid" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                <option value="">{{ __('booking.admin.events.all_courts') }}</option>
                @foreach($squares as $s)
                    <option value="{{ $s->sid }}" @selected((string) old('sid', $event->sid ?? '') === (string) $s->sid)>{{ $s->display_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="ui-field">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.events.capacity') }}</label>
            <input type="number" name="capacity" value="{{ old('capacity', $event->capacity ?? 0) }}" min="0" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
            <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.events.capacity_hint') }}</p>
        </div>
    </div>

    <div class="ui-form-divider">
        <p class="ui-section-label !mb-0">{{ __('booking.admin.events.notes_section') }}</p>
        <div class="ui-field">
        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="ef-notes">{{ __('booking.admin.events.notes') }}</label>
        <textarea id="ef-notes" name="notes" rows="2" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">{{ old('notes', $notes ?? '') }}</textarea>
        <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.events.notes_hint') }}</p>
        </div>
    </div>
</div>
@endif
