@extends('layouts.admin')
@section('admin-title', __('booking.admin.behavior'))
@section('admin-content')
@php
    // Choice groups keep the names and values of the former selects; an unknown or empty stored
    // value falls back to the first choice, as the browser did for the select.
    $choices = [
        'registration' => ['label' => __('booking.admin.registration'), 'help' => null, 'options' => ['0' => __('booking.admin.no'), '1' => __('booking.admin.yes')]],
        'activation' => ['label' => __('booking.admin.activation'), 'help' => __('booking.admin.activation_hint'), 'options' => [
            'immediate' => __('booking.admin.activation_immediate'),
            'manual' => __('booking.admin.activation_manual'),
            'manual-email' => __('booking.admin.activation_manual_email'),
            'email' => __('booking.admin.activation_email'),
        ]],
        'maintenance' => ['label' => __('booking.admin.maintenance'), 'help' => null, 'options' => ['0' => __('booking.admin.off'), '1' => __('booking.admin.on')]],
        'quotes_enabled' => ['label' => __('booking.admin.quotes_enabled'), 'help' => __('booking.admin.quotes_enabled_hint'), 'options' => ['0' => __('booking.admin.off'), '1' => __('booking.admin.on')]],
    ];
    foreach ($choices as $name => $choice) {
        $stored = (string) ($values[$name] ?? '');
        $choices[$name]['current'] = array_key_exists($stored, $choice['options']) ? $stored : (string) array_key_first($choice['options']);
    }
@endphp
<div class="ds-admin-stack ds-admin-form-page">
    <x-admin.page-header :title="__('booking.admin.behavior')" :eyebrow="__('booking.admin.nav.eyebrow')" />

    <form method="POST" action="{{ route('admin.config.behavior.update') }}" class="ds-admin-stack">
        @method('PUT')
        @csrf

        <x-ui.card :title="__('booking.admin.cfg.section_operation')" padding="lg" class="ds-admin-form-card">
            <div class="ds-admin-form-grid ds-admin-form-grid--2">
                @foreach($choices as $name => $choice)
                    <div class="ds-admin-choice">
                        <x-ui.option-group :legend="$choice['label']" :columns="2" :class="$name === 'activation' ? 'ds-admin-flags' : ''" :aria-describedby="$choice['help'] !== null ? 'cf-'.$name.'-help' : null">
                            @foreach($choice['options'] as $value => $label)
                                <x-ui.option-tile :name="$name" :value="$value" :label="$label" :checked="(string) $value === $choice['current']" />
                            @endforeach
                        </x-ui.option-group>
                        @if($choice['help'] !== null)
                            <p class="ds-field__help" id="cf-{{ $name }}-help">{{ $choice['help'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card :title="__('booking.admin.cfg.section_calendar')" padding="lg" class="ds-admin-form-card">
            <div class="ds-admin-split">
                <x-ui.field :label="__('booking.admin.calendar_hours.start')" for="cf-cal-start">
                    <x-ui.input id="cf-cal-start" name="calendar_time_start" type="time" :value="old('calendar_time_start', $values['calendar_time_start'])" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.calendar_hours.end')" for="cf-cal-end">
                    <x-ui.input id="cf-cal-end" name="calendar_time_end" type="time" :value="old('calendar_time_end', $values['calendar_time_end'])" />
                </x-ui.field>
            </div>
            <p class="ds-field__help">{{ __('booking.admin.calendar_hours.hint') }}</p>
            <x-ui.field :label="__('booking.admin.calendar_hide')" for="cf-hide" :help="__('booking.admin.calendar_hide_hint')">
                <x-ui.textarea id="cf-hide" name="calendar_hide" rows="5" :value="old('calendar_hide', $values['calendar_hide'])" />
            </x-ui.field>
        </x-ui.card>

        <x-ui.card :title="__('booking.admin.booking_rules.title')" padding="lg" class="ds-admin-form-card">
            <p class="ds-field__help">{{ __('booking.admin.booking_rules.intro') }}</p>
            <div class="ds-admin-form-grid">
                <x-ui.field :label="__('booking.admin.booking_rules.time_block')" for="cf-rule-time-block" :help="__('booking.admin.booking_rules.time_block_hint')">
                    <x-ui.input id="cf-rule-time-block" name="rule_time_block" type="number" min="1" max="1440" step="1" inputmode="numeric" :value="old('rule_time_block', $values['rule_time_block'])" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.booking_rules.min_range_book')" for="cf-rule-min-range" :help="__('booking.admin.booking_rules.min_range_book_hint')">
                    <x-ui.input id="cf-rule-min-range" name="rule_min_range_book" type="number" min="0" step="1" inputmode="numeric" :value="old('rule_min_range_book', $values['rule_min_range_book'])" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.booking_rules.range_book')" for="cf-rule-range" :help="__('booking.admin.booking_rules.range_book_hint')">
                    <x-ui.input id="cf-rule-range" name="rule_range_book" type="number" min="0" max="3650" step="1" inputmode="numeric" :value="old('rule_range_book', $values['rule_range_book'])" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.booking_rules.short_booking_window')" for="cf-rule-short" :help="__('booking.admin.booking_rules.short_booking_window_hint')">
                    <x-ui.input id="cf-rule-short" name="rule_short_booking_window" type="number" min="0" max="1440" step="1" inputmode="numeric" :value="old('rule_short_booking_window', $values['rule_short_booking_window'])" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.booking_rules.range_cancel')" for="cf-rule-cancel" :help="__('booking.admin.booking_rules.range_cancel_hint')">
                    <x-ui.input id="cf-rule-cancel" name="rule_range_cancel" type="number" min="0" max="8760" step="0.01" inputmode="decimal" :value="old('rule_range_cancel', $values['rule_range_cancel'])" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('booking.admin.time_blocks.title')" padding="lg" class="ds-admin-form-card">
            <div class="ds-admin-split">
                <x-ui.field :label="__('booking.admin.time_blocks.min_label')" for="cf-time-block-min" :help="__('booking.admin.time_blocks.min_hint')">
                    <x-ui.input id="cf-time-block-min" name="time_block_bookable_min" type="number" min="0" max="1440" step="1" inputmode="numeric" :value="old('time_block_bookable_min', $values['time_block_bookable_min'])" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.time_blocks.max_label')" for="cf-time-block-max" :help="__('booking.admin.time_blocks.max_hint')">
                    <x-ui.input id="cf-time-block-max" name="time_block_bookable_max" type="number" min="0" max="1440" step="1" inputmode="numeric" :value="old('time_block_bookable_max', $values['time_block_bookable_max'])" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('booking.admin.max_active_bookings.title')" padding="lg" class="ds-admin-form-card">
            <x-ui.field :label="__('booking.admin.max_active_bookings.label')" for="cf-max-active" :help="__('booking.admin.max_active_bookings.hint')">
                <x-ui.input id="cf-max-active" name="max_active_bookings" type="number" min="0" max="999" :value="old('max_active_bookings', $values['max_active_bookings'])" />
            </x-ui.field>
        </x-ui.card>

        <x-ui.card :title="__('booking.admin.peak_limit.title')" padding="lg" class="ds-admin-form-card">
            <div class="ds-admin-choice">
                <x-ui.option-group :columns="2" class="ds-admin-flags ds-admin-flags--single" aria-describedby="cf-peak-help">
                    <x-ui.option-tile type="checkbox" name="peak_limit_enabled" value="1" :label="__('booking.admin.peak_limit.enabled')" :checked="old('peak_limit_enabled', $values['peak_limit_enabled']) == '1'" />
                </x-ui.option-group>
                <p class="ds-field__help" id="cf-peak-help">{{ __('booking.admin.peak_limit.hint') }}</p>
            </div>
            <x-ui.field :label="__('booking.admin.peak_limit.max_label')" for="cf-peak-max" :help="__('booking.admin.peak_limit.max_hint')">
                <x-ui.input id="cf-peak-max" name="peak_limit_max" type="number" min="0" max="999" step="1" inputmode="numeric" :value="old('peak_limit_max', $values['peak_limit_max'])" />
            </x-ui.field>
            <div class="ds-admin-split">
                <x-ui.field :label="__('booking.admin.peak_limit.window_1_from')" for="cf-peak-w1-start">
                    <x-ui.input id="cf-peak-w1-start" name="peak_limit_w1_start" type="time" :value="old('peak_limit_w1_start', $values['peak_limit_w1_start'])" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.peak_limit.until')" for="cf-peak-w1-end">
                    <x-ui.input id="cf-peak-w1-end" name="peak_limit_w1_end" type="time" :value="old('peak_limit_w1_end', $values['peak_limit_w1_end'])" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.peak_limit.window_2_from')" for="cf-peak-w2-start">
                    <x-ui.input id="cf-peak-w2-start" name="peak_limit_w2_start" type="time" :value="old('peak_limit_w2_start', $values['peak_limit_w2_start'])" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.peak_limit.until')" for="cf-peak-w2-end">
                    <x-ui.input id="cf-peak-w2-end" name="peak_limit_w2_end" type="time" :value="old('peak_limit_w2_end', $values['peak_limit_w2_end'])" />
                </x-ui.field>
            </div>
            <p class="ds-field__help">{{ __('booking.admin.peak_limit.example') }}</p>
        </x-ui.card>

        <div class="ds-admin-actions ds-admin-actions--sticky">
            <div class="ds-admin-actions__group ds-admin-actions__group--main">
                <x-ui.button type="submit" :block="false" class="ds-admin-actions__save">{{ __('booking.admin.save') }}</x-ui.button>
            </div>
        </div>
    </form>
</div>
@endsection
