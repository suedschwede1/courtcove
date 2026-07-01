@extends('layouts.admin')
@section('admin-title', __('booking.admin.behavior'))
@section('admin-content')
<div class="flex flex-col gap-6">

    <h1 class="text-2xl font-bold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.behavior') }}</h1>

    <form method="POST" action="{{ route('admin.config.behavior.update') }}">
        @method('PUT')
        @csrf

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
                <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.peak_limit.title') }}</h2>
            </div>
            <div class="px-6 py-5 flex flex-col gap-4">

                <label class="flex items-center gap-2 text-sm text-[#151515] cursor-pointer">
                    <input type="checkbox" name="peak_limit_enabled" value="1"
                        @checked(old('peak_limit_enabled', $values['peak_limit_enabled']) == '1')>
                    {{ __('booking.admin.peak_limit.enabled') }}
                </label>
                <p class="text-xs text-[#6a6e73] -mt-2">{{ __('booking.admin.peak_limit.hint') }}</p>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.peak_limit.window_1_from') }}</label>
                        <input type="time" name="peak_limit_w1_start"
                            value="{{ old('peak_limit_w1_start', $values['peak_limit_w1_start']) }}"
                            class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.peak_limit.until') }}</label>
                        <input type="time" name="peak_limit_w1_end"
                            value="{{ old('peak_limit_w1_end', $values['peak_limit_w1_end']) }}"
                            class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.peak_limit.window_2_from') }}</label>
                        <input type="time" name="peak_limit_w2_start"
                            value="{{ old('peak_limit_w2_start', $values['peak_limit_w2_start']) }}"
                            class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.peak_limit.until') }}</label>
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
