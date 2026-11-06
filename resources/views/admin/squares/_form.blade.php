@csrf
@php
    $statusOptions = [
        'enabled' => __('booking.admin.squares.status_enabled'),
        'readonly' => __('booking.admin.squares.status_readonly'),
        'disabled' => __('booking.admin.squares.status_disabled'),
    ];
    $visibilityOptions = [
        'none' => __('booking.admin.squares.visibility_none'),
        'private' => __('booking.admin.squares.visibility_private'),
        'public' => __('booking.admin.squares.visibility_public'),
    ];
@endphp

<x-ui.card :title="__('booking.admin.squares.section_general')" padding="lg" class="ds-admin-form-card">
    <div class="ds-admin-form-grid">
        <x-ui.field :label="__('booking.admin.squares.name')" for="sf-name">
            <x-ui.input id="sf-name" name="name" :value="old('name', $form['name'])" />
        </x-ui.field>
        <x-ui.field :label="__('booking.admin.squares.display_name')" for="sf-alias" :help="__('booking.admin.squares.display_name_hint', ['example' => 'Platz1'])">
            <x-ui.input id="sf-alias" name="alias" :value="old('alias', $form['alias'])" />
        </x-ui.field>
        <x-ui.field :label="__('booking.admin.squares.status')" for="sf-status">
            <x-ui.select id="sf-status" name="status" :options="$statusOptions" :selected="old('status', $form['status'])" />
        </x-ui.field>
        <x-ui.field :label="__('booking.admin.squares.readonly_message')" for="sf-readonly-msg" :help="__('booking.admin.squares.readonly_message_hint')">
            <x-ui.input id="sf-readonly-msg" name="readonly_message" :value="old('readonly_message', $form['readonly_message'])" />
        </x-ui.field>
        <x-ui.field :label="__('booking.admin.squares.priority')" for="sf-priority">
            <x-ui.input id="sf-priority" name="priority" type="number" step="any" :value="old('priority', $form['priority'])" />
        </x-ui.field>
    </div>
</x-ui.card>

<x-ui.card :title="__('booking.admin.squares.section_range')" padding="lg" class="ds-admin-form-card">
    <div class="ds-admin-form-grid">
        <x-ui.field :label="__('booking.admin.squares.min_range_book')" for="sf-min-range-book">
            <x-ui.input id="sf-min-range-book" name="min_range_book" type="number" min="0" :value="old('min_range_book', $form['min_range_book'])" />
        </x-ui.field>
        <x-ui.field :label="__('booking.admin.squares.range_book')" for="sf-range-book" :help="__('booking.admin.squares.range_book_hint')">
            <x-ui.input id="sf-range-book" name="range_book" type="number" min="0" :value="old('range_book', $form['range_book'])" />
        </x-ui.field>
        <x-ui.field :label="__('booking.admin.squares.short_booking_window')" for="sf-short-booking-window" :help="__('booking.admin.squares.short_booking_window_hint')">
            <x-ui.input id="sf-short-booking-window" name="short_booking_window" type="number" min="0" :value="old('short_booking_window', $form['short_booking_window'])" />
        </x-ui.field>
        <x-ui.field :label="__('booking.admin.squares.range_cancel')" for="sf-range-cancel" :help="__('booking.admin.squares.range_cancel_hint')">
            <x-ui.input id="sf-range-cancel" name="range_cancel" type="number" step="0.01" min="0" :value="old('range_cancel', $form['range_cancel'])" />
        </x-ui.field>
    </div>
</x-ui.card>

<x-ui.card :title="__('booking.admin.squares.section_times')" padding="lg" class="ds-admin-form-card">
    <div class="ds-admin-form-grid">
        <x-ui.field :label="__('booking.admin.squares.time_start')" for="sf-time-start">
            <x-ui.input id="sf-time-start" name="time_start" type="time" :value="old('time_start', $form['time_start'])" />
        </x-ui.field>
        <x-ui.field :label="__('booking.admin.squares.time_end')" for="sf-time-end">
            <x-ui.input id="sf-time-end" name="time_end" type="time" :value="old('time_end', $form['time_end'])" />
        </x-ui.field>
        <x-ui.field :label="__('booking.admin.squares.time_block')" for="sf-time-block">
            <x-ui.input id="sf-time-block" name="time_block" type="number" min="0" :value="old('time_block', $form['time_block'])" />
        </x-ui.field>
    </div>
    <x-ui.option-group :columns="2" class="ds-admin-flags">
        <x-ui.option-tile type="checkbox" name="pseudo_time_block_bookable" value="1" :label="__('booking.admin.squares.pseudo_time_block_bookable')" :checked="(bool) old('pseudo_time_block_bookable', $form['pseudo_time_block_bookable'])" />
    </x-ui.option-group>
</x-ui.card>

<x-ui.card :title="__('booking.admin.squares.section_booking')" padding="lg" class="ds-admin-form-card">
    <div class="ds-admin-form-grid">
        <x-ui.field :label="__('booking.admin.squares.name_visibility')" for="sf-name-visibility">
            <x-ui.select id="sf-name-visibility" name="name_visibility" :options="$visibilityOptions" :selected="old('name_visibility', $form['name_visibility'])" />
        </x-ui.field>
    </div>
    <x-ui.option-group :columns="2" class="ds-admin-flags">
        <x-ui.option-tile type="checkbox" name="allow_notes" value="1" :label="__('booking.admin.squares.allow_notes')" :checked="(bool) old('allow_notes', $form['allow_notes'])" />
        {{-- Always shown; while the limit is globally off it shows the stored value, is disabled and is not saved. --}}
        @if($peakLimitGlobal ?? false)
            <x-ui.option-tile type="checkbox" name="peak_limit_enabled" value="1" :label="__('booking.admin.squares.peak_limit_enabled')" :checked="(bool) old('peak_limit_enabled', $form['peak_limit_enabled'])" />
        @else
            <x-ui.option-tile type="checkbox" name="peak_limit_enabled" value="1" :label="__('booking.admin.squares.peak_limit_enabled')" :checked="(bool) $form['peak_limit_enabled']" disabled aria-describedby="sf-peak-global-off" />
        @endif
    </x-ui.option-group>
    @unless($peakLimitGlobal ?? false)
        <p class="ds-field__help" id="sf-peak-global-off">{{ __('booking.admin.squares.peak_limit_global_off') }} (<a href="{{ route('admin.config.behavior.edit') }}" class="ds-admin-link">{{ __('booking.admin.squares.peak_limit_global_off_link') }}</a>)</p>
    @endunless
</x-ui.card>

<x-ui.card :title="__('booking.admin.squares.section_display')" padding="lg" class="ds-admin-form-card">
    <div class="ds-admin-form-grid">
        <x-ui.field :label="__('booking.admin.squares.label_free')" for="sf-label-free" :help="__('booking.admin.squares.label_free_hint')">
            <x-ui.input id="sf-label-free" name="label_free" :value="old('label_free', $form['label_free'])" />
        </x-ui.field>
    </div>
</x-ui.card>
