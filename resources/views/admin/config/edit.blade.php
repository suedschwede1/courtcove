@extends('layouts.admin')
@section('admin-title', __('booking.admin.config'))
@section('admin-content')
<div class="flex flex-col gap-6">

    <h1 class="text-2xl font-bold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.config') }}</h1>

    <form method="POST" action="{{ route('admin.config.update') }}">
        @method('PUT')
        @csrf

        {{-- Betreiber --}}
        <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-[#f0ede6]">
                <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.cfg.section_client') }}</h2>
            </div>
            <div class="px-6 py-5 flex flex-col gap-4">

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-client-full">{{ __('booking.admin.cfg.client_name_full') }}</label>
                    <input id="cf-client-full" type="text" name="client_name_full" value="{{ $values['client_name_full'] }}"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.cfg.client_name_full_hint') }}</p>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-client-short">{{ __('booking.admin.cfg.client_name_short') }}</label>
                    <input id="cf-client-short" type="text" name="client_name_short" value="{{ $values['client_name_short'] }}"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.cfg.client_name_short_hint') }}</p>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-email">{{ __('booking.admin.cfg.contact_email') }}</label>
                    <input id="cf-email" type="email" name="contact_email" value="{{ $values['contact_email'] }}"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.cfg.contact_email_hint') }}</p>
                    <label class="flex items-center gap-2 text-sm text-[#151515] cursor-pointer mt-1">
                        <input type="checkbox" name="client_email_cc" value="1" @checked((string) $values['client_email_cc'] === '1')>
                        {{ __('booking.admin.cfg.client_email_cc') }}
                    </label>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-phone">{{ __('booking.admin.cfg.client_phone') }}</label>
                    <input id="cf-phone" type="text" name="client_phone" value="{{ $values['client_phone'] }}"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.cfg.client_phone_hint') }}</p>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-website">{{ __('booking.admin.cfg.client_website') }}</label>
                    <input id="cf-website" type="url" name="client_website" value="{{ $values['client_website'] }}"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-contact">{{ __('booking.admin.cfg.client_website_contact') }}</label>
                    <input id="cf-contact" type="url" name="client_website_contact" value="{{ $values['client_website_contact'] }}"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-imprint">{{ __('booking.admin.cfg.client_website_imprint') }}</label>
                    <input id="cf-imprint" type="url" name="client_website_imprint" value="{{ $values['client_website_imprint'] }}"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-privacy">{{ __('booking.admin.cfg.client_website_privacy') }}</label>
                    <input id="cf-privacy" type="url" name="client_website_privacy" value="{{ $values['client_website_privacy'] }}"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>

            </div>
        </div>

        {{-- System --}}
        <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-[#f0ede6]">
                <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.cfg.section_system') }}</h2>
            </div>
            <div class="px-6 py-5 flex flex-col gap-4">

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-system-name">{{ __('booking.admin.cfg.system_name') }}</label>
                    <input id="cf-system-name" type="text" name="system_name" value="{{ $values['system_name'] }}"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.cfg.system_name_hint') }}</p>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-system-short">{{ __('booking.admin.cfg.service_name_short') }}</label>
                    <input id="cf-system-short" type="text" name="service_name_short" value="{{ $values['service_name_short'] }}"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.cfg.service_name_short_hint') }}</p>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-desc">{{ __('booking.admin.cfg.service_description') }}</label>
                    <input id="cf-desc" type="text" name="service_description" value="{{ $values['service_description'] }}"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>

            </div>
        </div>

        {{-- Bezeichnungen --}}
        <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-[#f0ede6]">
                <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.cfg.section_labels') }}</h2>
            </div>
            <div class="px-6 py-5 flex flex-col gap-4">

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-subject-type">{{ __('booking.admin.cfg.subject_type') }}</label>
                    <input id="cf-subject-type" type="text" name="subject_type" value="{{ $values['subject_type'] }}"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.cfg.subject_type_hint') }}</p>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.cfg.subject_square_type') }}</span>
                    <div class="flex gap-3 items-end">
                        <div class="flex flex-col gap-1">
                            <span class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.cfg.singular') }}</span>
                            <input type="text" name="subject_square_type" value="{{ $values['subject_square_type'] }}"
                                class="w-36 border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.cfg.plural') }}</span>
                            <input type="text" name="subject_square_plural" value="{{ $values['subject_square_plural'] }}"
                                class="w-36 border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.cfg.subject_unit') }}</span>
                    <div class="flex gap-3 items-end">
                        <div class="flex flex-col gap-1">
                            <span class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.cfg.singular') }}</span>
                            <input type="text" name="subject_unit" value="{{ $values['subject_unit'] }}"
                                class="w-36 border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.cfg.plural') }}</span>
                            <input type="text" name="subject_unit_plural" value="{{ $values['subject_unit_plural'] }}"
                                class="w-36 border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- Buchungsplan --}}
        <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-[#f0ede6]">
                <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.cfg.section_calendar') }}</h2>
            </div>
            <div class="px-6 py-5 flex flex-col gap-4">

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-days">{{ __('booking.admin.calendar_days') }}</label>
                    <input id="cf-days" type="number" name="calendar_days" min="1" max="31" value="{{ $values['calendar_days'] }}"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-hide">{{ __('booking.admin.calendar_hide') }}</label>
                    <textarea id="cf-hide" name="calendar_hide" rows="5"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">{{ $values['calendar_hide'] }}</textarea>
                    <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.calendar_hide_hint') }}</p>
                </div>

            </div>
        </div>


        {{-- Registrierungstexte --}}
        <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-[#f0ede6]">
                <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.cfg.section_registration_content') }}</h2>
            </div>
            <div class="px-6 py-5 flex flex-col gap-4">

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-registration-heading">{{ __('booking.admin.cfg.registration_heading') }}</label>
                    <input id="cf-registration-heading" type="text" name="registration_heading" value="{{ $values['registration_heading'] }}"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.cfg.registration_heading_hint') }}</p>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-registration-welcome">{{ __('booking.admin.cfg.registration_welcome') }}</label>
                    <input id="cf-registration-welcome" type="text" name="registration_welcome" value="{{ $values['registration_welcome'] }}"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.cfg.registration_system_placeholder_hint') }}</p>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-registration-intro">{{ __('booking.admin.cfg.registration_intro') }}</label>
                    <textarea id="cf-registration-intro" name="registration_intro" rows="4"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">{{ $values['registration_intro'] }}</textarea>
                    <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.cfg.registration_system_placeholder_hint') }}</p>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-registration-privacy">{{ __('booking.admin.cfg.registration_privacy') }}</label>
                    <textarea id="cf-registration-privacy" name="registration_privacy" rows="3"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">{{ $values['registration_privacy'] }}</textarea>
                    <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.cfg.registration_privacy_placeholder_hint') }}</p>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-registration-success">{{ __('booking.admin.cfg.registration_success') }}</label>
                    <textarea id="cf-registration-success" name="registration_success" rows="3"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">{{ $values['registration_success'] }}</textarea>
                    <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.cfg.registration_system_placeholder_hint') }}</p>
                </div>

            </div>
        </div>
        {{-- Betrieb --}}
        <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-[#f0ede6]">
                <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.cfg.section_operation') }}</h2>
            </div>
            <div class="px-6 py-5 flex flex-col gap-4">

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-reg">{{ __('booking.admin.registration') }}</label>
                    <select id="cf-reg" name="registration"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        <option value="0" @selected((string) $values['registration'] === '0')>{{ __('booking.admin.no') }}</option>
                        <option value="1" @selected((string) $values['registration'] === '1')>{{ __('booking.admin.yes') }}</option>
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-activation">{{ __('booking.admin.activation') }}</label>
                    <select id="cf-activation" name="activation"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        <option value="immediate" @selected(($values['activation'] ?? 'immediate') === 'immediate')>{{ __('booking.admin.activation_immediate') }}</option>
                        <option value="manual"       @selected(($values['activation'] ?? '') === 'manual')>{{ __('booking.admin.activation_manual') }}</option>
                        <option value="manual-email" @selected(($values['activation'] ?? '') === 'manual-email')>{{ __('booking.admin.activation_manual_email') }}</option>
                        <option value="email"        @selected(($values['activation'] ?? '') === 'email')>{{ __('booking.admin.activation_email') }}</option>
                    </select>
                    <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.activation_hint') }}</p>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="cf-maint">{{ __('booking.admin.maintenance') }}</label>
                    <select id="cf-maint" name="maintenance"
                        class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        <option value="0" @selected((string) $values['maintenance'] === '0')>{{ __('booking.admin.off') }}</option>
                        <option value="1" @selected((string) $values['maintenance'] === '1')>{{ __('booking.admin.on') }}</option>
                    </select>
                </div>

            </div>
        </div>

        {{-- Stoßzeiten --}}
        <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-[#f0ede6]">
                <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">Stoßzeiten-Limit</h2>
            </div>
            <div class="px-6 py-5 flex flex-col gap-4">

                <label class="flex items-center gap-2 text-sm text-[#151515] cursor-pointer">
                    <input type="checkbox" name="peak_limit_enabled" value="1"
                        @checked(old('peak_limit_enabled', $values['peak_limit_enabled']) == '1')>
                    Stoßzeiten-Limit global aktivieren
                </label>
                <p class="text-xs text-[#6a6e73] -mt-2">Wenn aktiv, zählen nur Buchungen in Stoßzeiten gegen das Buchungslimit. Pro Platz muss das Limit zusätzlich aktiviert werden.</p>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">Fenster 1 von</label>
                        <input type="time" name="peak_limit_w1_start"
                            value="{{ old('peak_limit_w1_start', $values['peak_limit_w1_start']) }}"
                            class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">bis</label>
                        <input type="time" name="peak_limit_w1_end"
                            value="{{ old('peak_limit_w1_end', $values['peak_limit_w1_end']) }}"
                            class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">Fenster 2 von</label>
                        <input type="time" name="peak_limit_w2_start"
                            value="{{ old('peak_limit_w2_start', $values['peak_limit_w2_start']) }}"
                            class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">bis</label>
                        <input type="time" name="peak_limit_w2_end"
                            value="{{ old('peak_limit_w2_end', $values['peak_limit_w2_end']) }}"
                            class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                </div>

            </div>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium px-5 py-2 rounded transition-colors">{{ __('booking.admin.save') }}</button>
        </div>

    </form>

</div>
@endsection

