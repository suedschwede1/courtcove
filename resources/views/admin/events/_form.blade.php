@csrf
@php
    $courtOptions = ['' => __('booking.admin.events.all_courts')];
    foreach ($squares as $s) {
        $courtOptions[$s->sid] = $s->display_name;
    }
@endphp
<x-ui.field :label="__('booking.admin.events.name')" for="ef-name">
    <x-ui.input id="ef-name" name="name" :value="old('name', $name ?? '')" required :placeholder="!empty($popup_mode) ? __('booking.admin.events.name_placeholder') : null" />
</x-ui.field>

<x-ui.field :label="__('booking.admin.events.description')" for="ef-description">
    <x-ui.textarea id="ef-description" name="description" rows="4" :value="old('description', $description ?? '')" />
</x-ui.field>

<div class="ds-admin-form-grid ds-admin-form-grid--2">
    <x-ui.field :label="__('booking.admin.events.court')" for="ef-sid">
        <x-ui.select id="ef-sid" name="sid" :options="$courtOptions" :selected="(string) old('sid', $event->sid ?? '')" />
    </x-ui.field>
    <x-ui.field :label="__('booking.admin.events.capacity')" for="ef-capacity" :help="!empty($popup_mode) ? __('booking.admin.events.capacity_zero_hint') : __('booking.admin.events.capacity_hint')">
        <x-ui.input id="ef-capacity" name="capacity" type="number" :value="old('capacity', $event->capacity ?? 0)" min="0" />
    </x-ui.field>
</div>

<p class="ds-admin-section-label">{{ __('booking.admin.events.period') }}</p>
{{-- DESIGN.md "Date and time fields": date start, time start, date end, time end in two columns. --}}
<div class="ds-admin-form-grid ds-admin-form-grid--2">
    <div class="ds-admin-form-grid__pair">
        <x-ui.field :label="__('booking.admin.events.date_start')" for="ef-date-start">
            <x-ui.input id="ef-date-start" name="date_start" type="date" :value="old('date_start', $date_start ?? '')" />
        </x-ui.field>
        <x-ui.field :label="__('booking.admin.events.time_start')" for="ef-time-start">
            <x-ui.input id="ef-time-start" name="time_start" type="time" :value="old('time_start', $time_start ?? '')" />
        </x-ui.field>
    </div>
    <div class="ds-admin-form-grid__pair">
        <x-ui.field :label="__('booking.admin.events.date_end')" for="ef-date-end">
            <x-ui.input id="ef-date-end" name="date_end" type="date" :value="old('date_end', $date_end ?? '')" />
        </x-ui.field>
        <x-ui.field :label="__('booking.admin.events.time_end')" for="ef-time-end">
            <x-ui.input id="ef-time-end" name="time_end" type="time" :value="old('time_end', $time_end ?? '')" />
        </x-ui.field>
    </div>
</div>

<x-ui.field :label="__('booking.admin.events.notes')" for="ef-notes" :help="__('booking.admin.events.notes_hint')">
    <x-ui.textarea id="ef-notes" name="notes" :rows="!empty($popup_mode) ? 4 : 2" :value="old('notes', $notes ?? '')" />
</x-ui.field>
