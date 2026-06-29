@csrf
<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="ef-name">{{ __('booking.admin.events.name') }}</label>
        <input id="ef-name" type="text" name="name" value="{{ old('name', $name ?? '') }}" required
               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
    </div>

    <div class="flex flex-col gap-1">
        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="ef-description">{{ __('booking.admin.events.description') }}</label>
        <textarea id="ef-description" name="description" rows="4"
                  class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">{{ old('description', $description ?? '') }}</textarea>
    </div>

    <div class="flex gap-3 items-end">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.events.date_start') }}</label>
            <input type="date" name="date_start" value="{{ old('date_start', $date_start ?? '') }}"
                   class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.events.time_start') }}</label>
            <input type="time" name="time_start" value="{{ old('time_start', $time_start ?? '') }}"
                   class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
    </div>

    <div class="flex gap-3 items-end">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.events.date_end') }}</label>
            <input type="date" name="date_end" value="{{ old('date_end', $date_end ?? '') }}"
                   class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.events.time_end') }}</label>
            <input type="time" name="time_end" value="{{ old('time_end', $time_end ?? '') }}"
                   class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
    </div>

    <div class="flex gap-3 items-end">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.events.court') }}</label>
            <select name="sid" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                <option value="">{{ __('booking.admin.events.all_courts') }}</option>
                @foreach($squares as $s)
                    <option value="{{ $s->sid }}" @selected((string) old('sid', $event->sid ?? '') === (string) $s->sid)>{{ $s->display_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.events.capacity') }}</label>
            <input type="number" name="capacity" value="{{ old('capacity', $event->capacity ?? 0) }}" min="0"
                   class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
            <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.events.capacity_hint') }}</p>
        </div>
    </div>

    <div class="flex flex-col gap-1">
        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="ef-notes">{{ __('booking.admin.events.notes') }}</label>
        <textarea id="ef-notes" name="notes" rows="2"
                  class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">{{ old('notes', $notes ?? '') }}</textarea>
        <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.events.notes_hint') }}</p>
    </div>
</div>
