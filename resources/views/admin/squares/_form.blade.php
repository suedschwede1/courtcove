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

<x-ui.card :title="__('booking.admin.squares.section_times')" padding="lg" class="ds-admin-form-card">
    <div class="ds-admin-form-grid">
        <x-ui.field :label="__('booking.admin.squares.time_start')" for="sf-time-start">
            <x-ui.input id="sf-time-start" name="time_start" type="time" :value="old('time_start', $form['time_start'])" />
        </x-ui.field>
        <x-ui.field :label="__('booking.admin.squares.time_end')" for="sf-time-end">
            <x-ui.input id="sf-time-end" name="time_end" type="time" :value="old('time_end', $form['time_end'])" />
        </x-ui.field>
    </div>
    <p class="ds-field__help">{{ __('booking.admin.squares.opening_hint') }}</p>
    <p class="ds-field__help">{{ __('booking.admin.squares.rules_moved_hint') }} <a href="{{ route('admin.config.behavior.edit') }}" class="ds-admin-link">{{ __('booking.admin.squares.peak_limit_global_off_link') }}</a></p>
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
