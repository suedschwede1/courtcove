@extends('layouts.admin')
@section('admin-title', __('booking.admin.whatsapp.title'))
@section('admin-content')
@php
    // Value of a field: the flashed input after a failed save, else what is stored (or the .env value when locked).
    $val = fn (string $field) => ($envLocked[$field] ?? false) ? $values[$field] : old($field, $values[$field]);
    $flag = fn (string $field) => ($envLocked[$field] ?? false) ? $values[$field] === '1' : (session()->hasOldInput() ? old($field) === '1' : $values[$field] === '1');
    // Help text of a locked field: where the value comes from.
    $help = fn (string $field, ?string $hint = null) => ($envLocked[$field] ?? false) ? __('booking.admin.whatsapp.from_env') : $hint;
    // Help text of a secret field: only its state, never a value.
    $secretHelp = function (string $field) use ($secretStates) {
        $s = $secretStates[$field];

        return match ($s['state']) {
            'env' => __('booking.admin.whatsapp.from_env').' – '.__('booking.admin.whatsapp.secret_env'),
            'set' => $field === 'auth_token'
                ? __('booking.admin.whatsapp.token_set')
                : __('booking.admin.whatsapp.secret_set', ['mask' => $s['mask']]),
            'unreadable' => __('booking.admin.whatsapp.secret_unreadable'),
            default => __('booking.admin.whatsapp.secret_unset'),
        };
    };
    $driverOptions = ['log' => __('booking.admin.whatsapp.driver_log'), 'live' => __('booking.admin.whatsapp.driver_live')];
    if ($driver === 'fake') {
        $driverOptions['fake'] = __('booking.admin.whatsapp.driver_fake');
    }
@endphp
<div class="ds-admin-stack ds-admin-form-page">
    <x-admin.page-header :title="__('booking.admin.whatsapp.title')" :eyebrow="__('booking.admin.nav.eyebrow')">
        <p class="ds-admin-note">{{ __('booking.admin.whatsapp.intro') }}</p>
    </x-admin.page-header>

    @if($effectiveDriver === 'log')
        <x-ui.notice tone="problem">{{ __('booking.admin.whatsapp.driver_log_notice') }}</x-ui.notice>
    @endif
    @if($configMissing)
        <x-ui.notice tone="attention">{{ __('booking.admin.whatsapp.config_missing') }}</x-ui.notice>
    @endif
    @if($enabledNow && $notReady !== null)
        <x-ui.notice tone="attention">{{ __('booking.admin.whatsapp.not_ready.'.$notReady) }}</x-ui.notice>
    @endif
    @if($problemCount > 0)
        <x-ui.notice tone="attention" data-whatsapp-warning="{{ $problemCount }}">{{ __('booking.admin.whatsapp.warning', ['count' => $problemCount]) }}</x-ui.notice>
    @endif
    @error('enabled')
        <x-ui.notice tone="problem">{{ $message }}</x-ui.notice>
    @enderror

    <form method="POST" action="{{ route('admin.whatsapp.update') }}" class="ds-admin-stack" autocomplete="off">
        @method('PUT')
        @csrf

        <x-ui.card :title="__('booking.admin.whatsapp.section_access')" padding="lg" class="ds-admin-form-card">
            <div class="ds-admin-form-grid ds-admin-form-grid--2">
                <x-ui.field :label="__('booking.admin.whatsapp.account_sid')" for="wa-account-sid" :help="$secretHelp('account_sid')">
                    <x-ui.input id="wa-account-sid" name="account_sid" :value="''" autocomplete="off" :disabled="$secretStates['account_sid']['state'] === 'env'" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.whatsapp.auth_token')" for="wa-auth-token" :help="$secretHelp('auth_token')">
                    <x-ui.input id="wa-auth-token" name="auth_token" type="password" :value="''" autocomplete="new-password" :disabled="$secretStates['auth_token']['state'] === 'env'" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.whatsapp.messaging_service_sid')" for="wa-service-sid" :help="$secretHelp('messaging_service_sid')">
                    <x-ui.input id="wa-service-sid" name="messaging_service_sid" :value="''" autocomplete="off" :disabled="$secretStates['messaging_service_sid']['state'] === 'env'" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.whatsapp.sender')" for="wa-sender" :help="$help('sender', __('booking.admin.whatsapp.sender_hint'))">
                    <x-ui.input id="wa-sender" name="sender" :value="$val('sender')" :disabled="$envLocked['sender']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.whatsapp.driver')" for="wa-driver" :help="$help('driver')">
                    <x-ui.select id="wa-driver" name="driver" :options="$driverOptions" :selected="$val('driver') !== '' ? $val('driver') : $driver" :disabled="$envLocked['driver']" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('booking.admin.whatsapp.section_switches')" padding="lg" class="ds-admin-form-card">
            <div class="ds-admin-choice">
                <x-ui.option-group :columns="2" class="ds-admin-flags ds-admin-flags--single" aria-describedby="wa-enabled-help">
                    <x-ui.option-tile type="checkbox" name="enabled" value="1" :label="__('booking.admin.whatsapp.enabled')" :checked="$flag('enabled')" />
                </x-ui.option-group>
                <p class="ds-field__help" id="wa-enabled-help">{{ __('booking.admin.whatsapp.enabled_hint') }}</p>
            </div>
            <div class="ds-admin-choice">
                <x-ui.option-group :columns="2" class="ds-admin-flags ds-admin-flags--single" :aria-describedby="$envLocked['sandbox'] ? 'wa-sandbox-help' : null">
                    <x-ui.option-tile type="checkbox" name="sandbox" value="1" :label="__('booking.admin.whatsapp.sandbox')" :checked="$flag('sandbox')" :disabled="$envLocked['sandbox']" />
                </x-ui.option-group>
                @if($envLocked['sandbox'])
                    <p class="ds-field__help" id="wa-sandbox-help">{{ __('booking.admin.whatsapp.from_env') }}</p>
                @endif
            </div>
            <div class="ds-admin-choice">
                <x-ui.option-group :columns="2" class="ds-admin-flags ds-admin-flags--single">
                    <x-ui.option-tile type="checkbox" name="followup_closed" value="1" :label="__('booking.admin.whatsapp.followup_closed')" :checked="$flag('followup_closed')" />
                </x-ui.option-group>
            </div>
            <div class="ds-admin-form-grid ds-admin-form-grid--2">
                <x-ui.field :label="__('booking.admin.whatsapp.sandbox_sender')" for="wa-sandbox-sender" :help="$help('sandbox_sender')">
                    <x-ui.input id="wa-sandbox-sender" name="sandbox_sender" :value="$val('sandbox_sender')" :disabled="$envLocked['sandbox_sender']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.whatsapp.sender_label')" for="wa-sender-label" :help="__('booking.admin.whatsapp.sender_label_hint')">
                    <x-ui.input id="wa-sender-label" name="sender_label" :value="$val('sender_label')" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('booking.admin.whatsapp.section_budget')" padding="lg" class="ds-admin-form-card">
            <div class="ds-admin-form-grid ds-admin-form-grid--2">
                <x-ui.field :label="__('booking.admin.whatsapp.budget_monthly')" for="wa-budget" :help="__('booking.admin.whatsapp.budget_monthly_hint')">
                    <x-ui.input id="wa-budget" name="budget_monthly" inputmode="decimal" :value="$val('budget_monthly')" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.whatsapp.price_estimate')" for="wa-price" :help="__('booking.admin.whatsapp.price_estimate_hint')">
                    <x-ui.input id="wa-price" name="price_estimate" inputmode="decimal" :value="$val('price_estimate')" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.whatsapp.daily_limit_per_user')" for="wa-daily">
                    <x-ui.input id="wa-daily" name="daily_limit_per_user" type="number" min="1" max="100" step="1" inputmode="numeric" :value="$val('daily_limit_per_user')" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.whatsapp.budget_alert_email')" for="wa-alert" :help="__('booking.admin.whatsapp.budget_alert_email_hint')">
                    <x-ui.input id="wa-alert" name="budget_alert_email" type="email" :value="$val('budget_alert_email')" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('booking.admin.whatsapp.section_language')" padding="lg" class="ds-admin-form-card">
            <div class="ds-admin-split">
                <x-ui.field :label="__('booking.admin.whatsapp.default_locale')" for="wa-locale">
                    <x-ui.select id="wa-locale" name="default_locale" :options="['de' => 'Deutsch', 'en' => 'English']" :selected="$val('default_locale') !== '' ? $val('default_locale') : 'de'" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.whatsapp.default_country')" for="wa-country" :help="__('booking.admin.whatsapp.default_country_hint')">
                    <x-ui.input id="wa-country" name="default_country" inputmode="numeric" :value="$val('default_country') !== '' ? $val('default_country') : '43'" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('booking.admin.whatsapp.section_templates')" padding="lg" class="ds-admin-form-card">
            <p class="ds-field__help">{{ __('booking.admin.whatsapp.templates_intro') }}</p>
            <div class="ds-admin-form-grid ds-admin-form-grid--2">
                @foreach($templateFields as $field => [, $type, $locale])
                    <x-ui.field :label="__('booking.admin.whatsapp.template_id', ['type' => __('booking.admin.whatsapp.templates.'.$type), 'locale' => strtoupper($locale)])" :for="'wa-'.$field">
                        <x-ui.input :id="'wa-'.$field" :name="$field" :value="old($field, $templates[$field])" />
                    </x-ui.field>
                @endforeach
            </div>
        </x-ui.card>

        <div class="ds-admin-actions ds-admin-actions--sticky">
            <div class="ds-admin-actions__group ds-admin-actions__group--main">
                <x-ui.button type="submit" :block="false" class="ds-admin-actions__save">{{ __('booking.admin.save') }}</x-ui.button>
            </div>
        </div>
    </form>
</div>
@endsection
