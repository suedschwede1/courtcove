@extends('layouts.admin')
@section('admin-title', __('booking.admin.config'))
@section('admin-content')
<h1>{{ __('booking.admin.config') }}</h1>
<hr class="admin-separator">
<form method="POST" action="{{ route('admin.config.update') }}" class="admin-form">
    @method('PUT')
    @csrf

    {{-- Betreiber --}}
    <div class="admin-form__section">
        <div class="admin-form__section-title">{{ __('booking.admin.cfg.section_client') }}</div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-client-full">{{ __('booking.admin.cfg.client_name_full') }}</label>
            <div class="admin-form__field">
                <input id="cf-client-full" type="text" name="client_name_full" value="{{ $values['client_name_full'] }}">
                <p class="admin-form__note">{{ __('booking.admin.cfg.client_name_full_hint') }}</p>
            </div>
        </div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-client-short">{{ __('booking.admin.cfg.client_name_short') }}</label>
            <div class="admin-form__field">
                <input id="cf-client-short" type="text" name="client_name_short" value="{{ $values['client_name_short'] }}">
                <p class="admin-form__note">{{ __('booking.admin.cfg.client_name_short_hint') }}</p>
            </div>
        </div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-email">{{ __('booking.admin.cfg.contact_email') }}</label>
            <div class="admin-form__field">
                <input id="cf-email" type="email" name="contact_email" value="{{ $values['contact_email'] }}">
                <p class="admin-form__note">{{ __('booking.admin.cfg.contact_email_hint') }}</p>
                <label class="admin-form__checkbox">
                    <input type="checkbox" name="client_email_cc" value="1" @checked((string) $values['client_email_cc'] === '1')>
                    {{ __('booking.admin.cfg.client_email_cc') }}
                </label>
            </div>
        </div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-phone">{{ __('booking.admin.cfg.client_phone') }}</label>
            <div class="admin-form__field">
                <input id="cf-phone" type="text" name="client_phone" value="{{ $values['client_phone'] }}">
                <p class="admin-form__note">{{ __('booking.admin.cfg.client_phone_hint') }}</p>
            </div>
        </div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-website">{{ __('booking.admin.cfg.client_website') }}</label>
            <div class="admin-form__field"><input id="cf-website" type="url" name="client_website" value="{{ $values['client_website'] }}"></div>
        </div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-contact">{{ __('booking.admin.cfg.client_website_contact') }}</label>
            <div class="admin-form__field"><input id="cf-contact" type="url" name="client_website_contact" value="{{ $values['client_website_contact'] }}"></div>
        </div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-imprint">{{ __('booking.admin.cfg.client_website_imprint') }}</label>
            <div class="admin-form__field"><input id="cf-imprint" type="url" name="client_website_imprint" value="{{ $values['client_website_imprint'] }}"></div>
        </div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-privacy">{{ __('booking.admin.cfg.client_website_privacy') }}</label>
            <div class="admin-form__field"><input id="cf-privacy" type="url" name="client_website_privacy" value="{{ $values['client_website_privacy'] }}"></div>
        </div>
    </div>

    {{-- System --}}
    <div class="admin-form__section">
        <div class="admin-form__section-title">{{ __('booking.admin.cfg.section_system') }}</div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-system-name">{{ __('booking.admin.cfg.system_name') }}</label>
            <div class="admin-form__field">
                <input id="cf-system-name" type="text" name="system_name" value="{{ $values['system_name'] }}">
                <p class="admin-form__note">{{ __('booking.admin.cfg.system_name_hint') }}</p>
            </div>
        </div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-system-short">{{ __('booking.admin.cfg.service_name_short') }}</label>
            <div class="admin-form__field">
                <input id="cf-system-short" type="text" name="service_name_short" value="{{ $values['service_name_short'] }}">
                <p class="admin-form__note">{{ __('booking.admin.cfg.service_name_short_hint') }}</p>
            </div>
        </div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-desc">{{ __('booking.admin.cfg.service_description') }}</label>
            <div class="admin-form__field"><input id="cf-desc" type="text" name="service_description" value="{{ $values['service_description'] }}"></div>
        </div>
    </div>

    {{-- Bezeichnungen --}}
    <div class="admin-form__section">
        <div class="admin-form__section-title">{{ __('booking.admin.cfg.section_labels') }}</div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-subject-type">{{ __('booking.admin.cfg.subject_type') }}</label>
            <div class="admin-form__field">
                <input id="cf-subject-type" type="text" name="subject_type" value="{{ $values['subject_type'] }}">
                <p class="admin-form__note">{{ __('booking.admin.cfg.subject_type_hint') }}</p>
            </div>
        </div>
        <div class="admin-form__row">
            <label class="admin-form__label">{{ __('booking.admin.cfg.subject_square_type') }}</label>
            <div class="admin-form__field admin-form__field--flex">
                <div class="admin-form__inline-group">
                    <span class="admin-form__inline-label">{{ __('booking.admin.cfg.singular') }}</span>
                    <input type="text" name="subject_square_type" value="{{ $values['subject_square_type'] }}" style="width:140px">
                </div>
                <div class="admin-form__inline-group">
                    <span class="admin-form__inline-label">{{ __('booking.admin.cfg.plural') }}</span>
                    <input type="text" name="subject_square_plural" value="{{ $values['subject_square_plural'] }}" style="width:140px">
                </div>
            </div>
        </div>
        <div class="admin-form__row">
            <label class="admin-form__label">{{ __('booking.admin.cfg.subject_unit') }}</label>
            <div class="admin-form__field admin-form__field--flex">
                <div class="admin-form__inline-group">
                    <span class="admin-form__inline-label">{{ __('booking.admin.cfg.singular') }}</span>
                    <input type="text" name="subject_unit" value="{{ $values['subject_unit'] }}" style="width:140px">
                </div>
                <div class="admin-form__inline-group">
                    <span class="admin-form__inline-label">{{ __('booking.admin.cfg.plural') }}</span>
                    <input type="text" name="subject_unit_plural" value="{{ $values['subject_unit_plural'] }}" style="width:140px">
                </div>
            </div>
        </div>
    </div>

    {{-- Buchungsplan --}}
    <div class="admin-form__section">
        <div class="admin-form__section-title">{{ __('booking.admin.cfg.section_calendar') }}</div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-days">{{ __('booking.admin.calendar_days') }}</label>
            <div class="admin-form__field"><input id="cf-days" type="number" name="calendar_days" min="1" max="31" value="{{ $values['calendar_days'] }}"></div>
        </div>
        <div class="admin-form__row">
            <label class="admin-form__label" for="cf-hide">{{ __('booking.admin.calendar_hide') }}</label>
            <div class="admin-form__field">
                <textarea id="cf-hide" name="calendar_hide" rows="5">{{ $values['calendar_hide'] }}</textarea>
                <p class="admin-form__note">{{ __('booking.admin.calendar_hide_hint') }}</p>
            </div>
        </div>
    </div>

    {{-- Betrieb --}}
    <div class="admin-form__section">
        <div class="admin-form__section-title">{{ __('booking.admin.cfg.section_operation') }}</div>
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
            <label class="admin-form__label" for="cf-activation">{{ __('booking.admin.activation') }}</label>
            <div class="admin-form__field">
                <select id="cf-activation" name="activation">
                    <option value="immediate" @selected(($values['activation'] ?? 'immediate') === 'immediate')>{{ __('booking.admin.activation_immediate') }}</option>
                    <option value="manual"       @selected(($values['activation'] ?? '') === 'manual')>{{ __('booking.admin.activation_manual') }}</option>
                    <option value="manual-email" @selected(($values['activation'] ?? '') === 'manual-email')>{{ __('booking.admin.activation_manual_email') }}</option>
                    <option value="email"        @selected(($values['activation'] ?? '') === 'email')>{{ __('booking.admin.activation_email') }}</option>
                </select>
                <p class="admin-form__note">{{ __('booking.admin.activation_hint') }}</p>
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
    </div>

    <div class="admin-form__actions">
        <button type="submit" class="admin-btn-primary">{{ __('booking.admin.save') }}</button>
    </div>
</form>
@endsection
