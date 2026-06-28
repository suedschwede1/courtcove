@extends('layouts.admin')
@section('admin-title', __('booking.admin.config'))
@section('admin-content')
<h1>{{ __('booking.admin.config') }}</h1>
<hr class="admin-separator">
<form method="POST" action="{{ route('admin.config.update') }}" class="admin-form">
    @method('PUT')
    @csrf

    <div class="admin-form__section">
        <div class="admin-form__section-title">System</div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-system-name">{{ __('booking.admin.system_name') }}</label>
            <div class="admin-form__field"><input id="cf-system-name" type="text" name="system_name" value="{{ $values['system_name'] }}"></div>
        </div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-client-name">{{ __('booking.admin.client_name_full') }}</label>
            <div class="admin-form__field"><input id="cf-client-name" type="text" name="client_name_full" value="{{ $values['client_name_full'] }}"></div>
        </div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-email">{{ __('booking.admin.contact_email') }}</label>
            <div class="admin-form__field"><input id="cf-email" type="email" name="contact_email" value="{{ $values['contact_email'] }}"></div>
        </div>
    </div>

    <div class="admin-form__section">
        <div class="admin-form__section-title">Buchungsplan</div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-days">{{ __('booking.admin.calendar_days') }}</label>
            <div class="admin-form__field"><input id="cf-days" type="number" name="calendar_days" min="1" max="31" value="{{ $values['calendar_days'] }}"></div>
        </div>
    </div>

    <div class="admin-form__section">
        <div class="admin-form__section-title">Betrieb</div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-reg">{{ __('booking.admin.registration') }}</label>
            <div class="admin-form__field">
                <select id="cf-reg" name="registration">
                    <option value="0" @selected((string) $values['registration'] === '0')>{{ __('booking.admin.no') }}</option>
                    <option value="1" @selected((string) $values['registration'] === '1')>{{ __('booking.admin.yes') }}</option>
                </select>
            </div>
        </div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-maint">{{ __('booking.admin.maintenance') }}</label>
            <div class="admin-form__field">
                <select id="cf-maint" name="maintenance">
                    <option value="0" @selected((string) $values['maintenance'] === '0')>{{ __('booking.admin.off') }}</option>
                    <option value="1" @selected((string) $values['maintenance'] === '1')>{{ __('booking.admin.on') }}</option>
                </select>
            </div>
        </div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-activation">{{ __('booking.admin.activation') }}</label>
            <div class="admin-form__field">
                <select id="cf-activation" name="activation">
                    <option value="immediate" @selected(($values['activation'] ?? 'immediate') === 'immediate')>{{ __('booking.admin.activation_immediate') }}</option>
                    <option value="manual"    @selected(($values['activation'] ?? '') === 'manual')>{{ __('booking.admin.activation_manual') }}</option>
                    <option value="manual-email" @selected(($values['activation'] ?? '') === 'manual-email')>{{ __('booking.admin.activation_manual_email') }}</option>
                    <option value="email"     @selected(($values['activation'] ?? '') === 'email')>{{ __('booking.admin.activation_email') }}</option>
                </select>
                <p class="admin-form__note">{{ __('booking.admin.activation_hint') }}</p>
            </div>
        </div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-hide">{{ __('booking.admin.calendar_hide') }}</label>
            <div class="admin-form__field">
                <textarea id="cf-hide" name="calendar_hide" rows="5">{{ $values['calendar_hide'] }}</textarea>
                <p class="admin-form__note">{{ __('booking.admin.calendar_hide_hint') }}</p>
            </div>
        </div>
    </div>

    <div class="admin-form__actions">
        <button type="submit" class="admin-btn-primary">{{ __('booking.admin.save') }}</button>
    </div>
</form>
@endsection
