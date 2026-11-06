@extends('layouts.admin')
@section('admin-title', __('booking.admin.texts'))
@section('admin-content')
<div class="ds-admin-stack ds-admin-form-page">
    <x-admin.page-header :title="__('booking.admin.texts')" :eyebrow="__('booking.admin.nav.eyebrow')" />

    <form method="POST" action="{{ route('admin.config.update') }}" class="ds-admin-stack">
        @method('PUT')
        @csrf

        <x-ui.card :title="__('booking.admin.cfg.section_client')" padding="lg" class="ds-admin-form-card">
            <div class="ds-admin-form-grid ds-admin-form-grid--2">
                <x-ui.field :label="__('booking.admin.client_name_full')" for="cf-client-full" :help="__('booking.admin.cfg.client_name_full_hint')">
                    <x-ui.input id="cf-client-full" name="client_name_full" :value="$values['client_name_full']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.client_name_short')" for="cf-client-short" :help="__('booking.admin.cfg.client_name_short_hint')">
                    <x-ui.input id="cf-client-short" name="client_name_short" :value="$values['client_name_short']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.contact_email')" for="cf-email" :help="__('booking.admin.cfg.contact_email_hint')">
                    <x-ui.input id="cf-email" name="contact_email" type="email" :value="$values['contact_email']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.client_phone')" for="cf-phone" :help="__('booking.admin.cfg.client_phone_hint')">
                    <x-ui.input id="cf-phone" name="client_phone" :value="$values['client_phone']" />
                </x-ui.field>
                <x-ui.option-group :columns="2" class="ds-admin-flags ds-admin-flags--single ds-admin-form-grid__wide">
                    <x-ui.option-tile type="checkbox" name="client_email_cc" value="1" :label="__('booking.admin.cfg.client_email_cc')" :checked="(string) $values['client_email_cc'] === '1'" />
                </x-ui.option-group>
                <x-ui.field :label="__('booking.admin.cfg.client_website')" for="cf-website">
                    <x-ui.input id="cf-website" name="client_website" type="url" :value="$values['client_website']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.client_website_contact')" for="cf-contact">
                    <x-ui.input id="cf-contact" name="client_website_contact" type="url" :value="$values['client_website_contact']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.client_website_imprint')" for="cf-imprint">
                    <x-ui.input id="cf-imprint" name="client_website_imprint" type="url" :value="$values['client_website_imprint']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.client_website_privacy')" for="cf-privacy">
                    <x-ui.input id="cf-privacy" name="client_website_privacy" type="url" :value="$values['client_website_privacy']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.logo_path')" for="cf-logo-path" :help="__('booking.admin.cfg.logo_path_hint')" class="ds-admin-form-grid__wide">
                    <x-ui.input id="cf-logo-path" name="logo_path" :value="$values['logo_path']" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('booking.admin.cfg.section_system')" padding="lg" class="ds-admin-form-card">
            <div class="ds-admin-form-grid ds-admin-form-grid--2">
                <x-ui.field :label="__('booking.admin.system_name')" for="cf-system-name" :help="__('booking.admin.cfg.system_name_hint')">
                    <x-ui.input id="cf-system-name" name="system_name" :value="$values['system_name']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.service_name_short')" for="cf-system-short" :help="__('booking.admin.cfg.service_name_short_hint')">
                    <x-ui.input id="cf-system-short" name="service_name_short" :value="$values['service_name_short']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.service_description')" for="cf-desc" class="ds-admin-form-grid__wide">
                    <x-ui.input id="cf-desc" name="service_description" :value="$values['service_description']" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('booking.admin.cfg.section_labels')" padding="lg" class="ds-admin-form-card">
            <div class="ds-admin-form-grid ds-admin-form-grid--2">
                <x-ui.field :label="__('booking.admin.cfg.subject_type')" for="cf-subject-type" :help="__('booking.admin.cfg.subject_type_hint')" class="ds-admin-form-grid__wide">
                    <x-ui.input id="cf-subject-type" name="subject_type" :value="$values['subject_type']" />
                </x-ui.field>
                <fieldset class="ds-options">
                    <legend class="ds-field__label">{{ __('booking.admin.cfg.subject_square_type') }}</legend>
                    <div class="ds-admin-split">
                        <x-ui.field :label="__('booking.admin.cfg.singular')" for="cf-square-singular">
                            <x-ui.input id="cf-square-singular" name="subject_square_type" :value="$values['subject_square_type']" />
                        </x-ui.field>
                        <x-ui.field :label="__('booking.admin.cfg.plural')" for="cf-square-plural">
                            <x-ui.input id="cf-square-plural" name="subject_square_plural" :value="$values['subject_square_plural']" />
                        </x-ui.field>
                    </div>
                </fieldset>
                <fieldset class="ds-options">
                    <legend class="ds-field__label">{{ __('booking.admin.cfg.subject_unit') }}</legend>
                    <div class="ds-admin-split">
                        <x-ui.field :label="__('booking.admin.cfg.singular')" for="cf-unit-singular">
                            <x-ui.input id="cf-unit-singular" name="subject_unit" :value="$values['subject_unit']" />
                        </x-ui.field>
                        <x-ui.field :label="__('booking.admin.cfg.plural')" for="cf-unit-plural">
                            <x-ui.input id="cf-unit-plural" name="subject_unit_plural" :value="$values['subject_unit_plural']" />
                        </x-ui.field>
                    </div>
                </fieldset>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('booking.admin.cfg.section_registration_content')" padding="lg" class="ds-admin-form-card">
            <div class="ds-admin-form-grid ds-admin-form-grid--2">
                <x-ui.field :label="__('booking.admin.cfg.registration_heading')" for="cf-registration-heading" :help="__('booking.admin.cfg.registration_heading_hint')">
                    <x-ui.input id="cf-registration-heading" name="registration_heading" :value="$values['registration_heading']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.registration_welcome')" for="cf-registration-welcome" :help="__('booking.admin.cfg.registration_system_placeholder_hint')">
                    <x-ui.input id="cf-registration-welcome" name="registration_welcome" :value="$values['registration_welcome']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.registration_intro')" for="cf-registration-intro" :help="__('booking.admin.cfg.registration_system_placeholder_hint')" class="ds-admin-form-grid__wide">
                    <x-ui.textarea id="cf-registration-intro" name="registration_intro" rows="4" :value="$values['registration_intro']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.registration_email_help')" for="cf-registration-email-help" :help="__('booking.admin.cfg.registration_system_placeholder_hint')" class="ds-admin-form-grid__wide">
                    <x-ui.input id="cf-registration-email-help" name="registration_email_help" :value="$values['registration_email_help']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.registration_privacy')" for="cf-registration-privacy" :help="__('booking.admin.cfg.registration_privacy_placeholder_hint')">
                    <x-ui.textarea id="cf-registration-privacy" name="registration_privacy" rows="3" :value="$values['registration_privacy']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.registration_success')" for="cf-registration-success" :help="__('booking.admin.cfg.registration_system_placeholder_hint')">
                    <x-ui.textarea id="cf-registration-success" name="registration_success" rows="3" :value="$values['registration_success']" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('booking.admin.cfg.section_tournament_mail')" padding="lg" class="ds-admin-form-card">
            <div class="ds-admin-form-grid ds-admin-form-grid--2">
                <x-ui.field :label="__('booking.admin.cfg.tournament_mail_from_address')" for="cf-tm-from-address" :help="__('booking.admin.cfg.tournament_mail_from_address_hint')">
                    <x-ui.input id="cf-tm-from-address" name="tournament_mail_from_address" type="email" :value="$values['tournament_mail_from_address']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.tournament_mail_from_name')" for="cf-tm-from-name" :help="__('booking.admin.cfg.tournament_mail_from_name_hint')">
                    <x-ui.input id="cf-tm-from-name" name="tournament_mail_from_name" :value="$values['tournament_mail_from_name']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.tournament_mail_reply_to_address')" for="cf-tm-reply-address" :help="__('booking.admin.cfg.tournament_mail_reply_to_address_hint')">
                    <x-ui.input id="cf-tm-reply-address" name="tournament_mail_reply_to_address" type="email" :value="$values['tournament_mail_reply_to_address']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.tournament_mail_reply_to_name')" for="cf-tm-reply-name">
                    <x-ui.input id="cf-tm-reply-name" name="tournament_mail_reply_to_name" :value="$values['tournament_mail_reply_to_name']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.tournament_mail_signature_club')" for="cf-tm-signature-club" :help="__('booking.admin.cfg.tournament_mail_signature_club_hint')">
                    <x-ui.input id="cf-tm-signature-club" name="tournament_mail_signature_club" :value="$values['tournament_mail_signature_club']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.tournament_mail_signature_url')" for="cf-tm-signature-url" :help="__('booking.admin.cfg.tournament_mail_signature_url_hint')">
                    <x-ui.input id="cf-tm-signature-url" name="tournament_mail_signature_url" type="url" :value="$values['tournament_mail_signature_url']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.tournament_mail_signature_text')" for="cf-tm-signature-text" :help="__('booking.admin.cfg.tournament_mail_signature_text_hint')" class="ds-admin-form-grid__wide">
                    <x-ui.textarea id="cf-tm-signature-text" name="tournament_mail_signature_text" rows="3" :value="$values['tournament_mail_signature_text']" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('booking.admin.cfg.section_system_mail')" padding="lg" class="ds-admin-form-card">
            <div class="ds-admin-form-grid ds-admin-form-grid--2">
                <x-ui.field :label="__('booking.admin.cfg.system_mail_booking_reply_to')" for="cf-sm-booking-reply" :help="__('booking.admin.cfg.system_mail_booking_reply_to_hint')">
                    <x-ui.input id="cf-sm-booking-reply" name="system_mail_booking_reply_to" type="email" :value="$values['system_mail_booking_reply_to']" />
                </x-ui.field>
                <x-ui.field :label="__('booking.admin.cfg.system_mail_account_reply_to')" for="cf-sm-account-reply" :help="__('booking.admin.cfg.system_mail_account_reply_to_hint')">
                    <x-ui.input id="cf-sm-account-reply" name="system_mail_account_reply_to" type="email" :value="$values['system_mail_account_reply_to']" />
                </x-ui.field>
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
