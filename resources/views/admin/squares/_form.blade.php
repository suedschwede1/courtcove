@csrf

{{-- Section 1: General --}}
<div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-[#f0ede6]">
        <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.squares.section_general') }}</h2>
    </div>
    <div class="px-6 py-5 flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="sf-name">{{ __('booking.admin.squares.name') }}</label>
            <input id="sf-name" type="text" name="name" value="{{ old('name', $form['name']) }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="sf-alias">{{ __('booking.admin.squares.display_name') }}</label>
            <input id="sf-alias" type="text" name="alias" value="{{ old('alias', $form['alias']) }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
            <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.squares.display_name_hint', ['example' => 'Platz1']) }}</p>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="sf-status">{{ __('booking.admin.squares.status') }}</label>
            <select id="sf-status" name="status" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                @foreach(['enabled' => __('booking.admin.squares.status_enabled'), 'readonly' => __('booking.admin.squares.status_readonly'), 'disabled' => __('booking.admin.squares.status_disabled')] as $val => $lbl)
                    <option value="{{ $val }}" @selected(old('status', $form['status']) === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="sf-readonly-msg">{{ __('booking.admin.squares.readonly_message') }}</label>
            <input id="sf-readonly-msg" type="text" name="readonly_message" value="{{ old('readonly_message', $form['readonly_message']) }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
            <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.squares.readonly_message_hint') }}</p>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="sf-priority">{{ __('booking.admin.squares.priority') }}</label>
            <input id="sf-priority" type="number" step="any" name="priority" value="{{ old('priority', $form['priority']) }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
    </div>
</div>

{{-- Section 2: Booking --}}
<div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-[#f0ede6]">
        <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.squares.section_booking') }}</h2>
    </div>
    <div class="px-6 py-5 flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="sf-ask-names">{{ __('booking.admin.squares.ask_names') }}</label>
            @php $askLabels = ['' => __('booking.admin.squares.ask_names_none'), 'optional-names' => __('booking.admin.squares.ask_names_optional'), 'optional-names-email' => __('booking.admin.squares.ask_names_optional_email'), 'optional-names-phone' => __('booking.admin.squares.ask_names_optional_phone'), 'optional-names-email-phone' => __('booking.admin.squares.ask_names_optional_email_phone'), 'required-names' => __('booking.admin.squares.ask_names_required'), 'required-names-email' => __('booking.admin.squares.ask_names_required_email'), 'required-names-phone' => __('booking.admin.squares.ask_names_required_phone'), 'required-names-email-phone' => __('booking.admin.squares.ask_names_required_email_phone')]; @endphp
            <select id="sf-ask-names" name="capacity_ask_names" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                @foreach($askLabels as $val => $lbl)
                    <option value="{{ $val }}" @selected(old('capacity_ask_names', $form['capacity_ask_names']) === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <label class="flex items-center gap-2 text-sm text-[#151515] cursor-pointer">
            <input type="checkbox" name="allow_notes" value="1" @checked(old('allow_notes', $form['allow_notes']))> {{ __('booking.admin.squares.allow_notes') }}
        </label>
        @if($peakLimitGlobal ?? false)
        <label class="flex items-center gap-2 text-sm text-[#151515] cursor-pointer">
            <input type="checkbox" name="peak_limit_enabled" value="1"
                @checked(old('peak_limit_enabled', $form['peak_limit_enabled']))>
            Stoßzeiten-Limit für diesen Platz aktivieren
        </label>
        @endif
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="sf-name-visibility">{{ __('booking.admin.squares.name_visibility') }}</label>
            <select id="sf-name-visibility" name="name_visibility" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                @foreach(['none' => __('booking.admin.squares.visibility_none'), 'private' => __('booking.admin.squares.visibility_private'), 'public' => __('booking.admin.squares.visibility_public')] as $val => $lbl)
                    <option value="{{ $val }}" @selected(old('name_visibility', $form['name_visibility']) === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

{{-- Section 3: Times --}}
<div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-[#f0ede6]">
        <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.squares.section_times') }}</h2>
    </div>
    <div class="px-6 py-5 flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="sf-time-start">{{ __('booking.admin.squares.time_start') }}</label>
            <input id="sf-time-start" type="time" name="time_start" value="{{ old('time_start', $form['time_start']) }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="sf-time-end">{{ __('booking.admin.squares.time_end') }}</label>
            <input id="sf-time-end" type="time" name="time_end" value="{{ old('time_end', $form['time_end']) }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="sf-time-block">{{ __('booking.admin.squares.time_block') }}</label>
            <input id="sf-time-block" type="number" min="0" name="time_block" value="{{ old('time_block', $form['time_block']) }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="sf-time-block-bookable">{{ __('booking.admin.squares.time_block_bookable') }}</label>
            <input id="sf-time-block-bookable" type="number" min="0" name="time_block_bookable" value="{{ old('time_block_bookable', $form['time_block_bookable']) }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
        <label class="flex items-center gap-2 text-sm text-[#151515] cursor-pointer">
            <input type="checkbox" name="pseudo_time_block_bookable" value="1" @checked(old('pseudo_time_block_bookable', $form['pseudo_time_block_bookable']))> {{ __('booking.admin.squares.pseudo_time_block_bookable') }}
        </label>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="sf-time-block-max">{{ __('booking.admin.squares.time_block_bookable_max') }}</label>
            <input id="sf-time-block-max" type="number" min="0" name="time_block_bookable_max" value="{{ old('time_block_bookable_max', $form['time_block_bookable_max']) }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="sf-min-range-book">{{ __('booking.admin.squares.min_range_book') }}</label>
            <input id="sf-min-range-book" type="number" min="0" name="min_range_book" value="{{ old('min_range_book', $form['min_range_book']) }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="sf-range-book">{{ __('booking.admin.squares.range_book') }}</label>
            <input id="sf-range-book" type="number" min="0" name="range_book" value="{{ old('range_book', $form['range_book']) }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
            <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.squares.range_book_hint') }}</p>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="sf-short-booking-window">{{ __('booking.admin.squares.short_booking_window') }}</label>
            <input id="sf-short-booking-window" type="number" min="0" name="short_booking_window" value="{{ old('short_booking_window', $form['short_booking_window']) }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
            <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.squares.short_booking_window_hint') }}</p>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="sf-max-bookings">{{ __('booking.admin.squares.max_active_bookings') }}</label>
            <input id="sf-max-bookings" type="number" min="0" name="max_active_bookings" value="{{ old('max_active_bookings', $form['max_active_bookings']) }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
            <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.squares.max_active_bookings_hint') }}</p>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="sf-range-cancel">{{ __('booking.admin.squares.range_cancel') }}</label>
            <input id="sf-range-cancel" type="number" step="0.01" min="0" name="range_cancel" value="{{ old('range_cancel', $form['range_cancel']) }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
            <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.squares.range_cancel_hint') }}</p>
        </div>
    </div>
</div>

{{-- Section 4: Display --}}
<div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-[#f0ede6]">
        <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.squares.section_display') }}</h2>
    </div>
    <div class="px-6 py-5 flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="sf-label-free">{{ __('booking.admin.squares.label_free') }}</label>
            <input id="sf-label-free" type="text" name="label_free" value="{{ old('label_free', $form['label_free']) }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
            <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.squares.label_free_hint') }}</p>
        </div>
    </div>
</div>

