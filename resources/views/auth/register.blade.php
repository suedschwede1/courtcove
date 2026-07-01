<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ str_replace(':system', $bookingName, __('booking.register.title')) }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Red+Hat+Display:wght@600;700&family=Red+Hat+Text:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/booking.css') }}">
</head>
<body style="font-family: var(--font-body); background: var(--ui-bg); min-height: 100vh;">
@php
    $registrationHeading = str_replace(':system', $bookingName, $registrationContent['heading']);
    $registrationWelcome = str_replace(':system', $bookingName, $registrationContent['welcome']);
    $registrationIntro = str_replace(':system', $bookingName, $registrationContent['intro']);
    $registrationEmailHelp = str_replace(':system', $bookingName, $registrationContent['email_help']);
    $privacyUrl = trim((string) \App\Models\Option::getValue('client.website.privacy', '#'));
    $privacyLink = '<a href="'.e($privacyUrl !== '' ? $privacyUrl : '#').'" class="text-[#bf4316] underline" target="_blank" rel="noopener noreferrer">'.e(__('booking.register.privacy_link')).'</a>';
    $registrationPrivacy = str_replace([':system', ':privacy_policy'], [$bookingName, $privacyLink], $registrationContent['privacy']);
@endphp

<div class="max-w-3xl mx-auto px-4 py-6 sm:py-10">
    <div class="mb-6 sm:mb-8">
        <a href="{{ route('calendar.index') }}" class="flex items-center gap-3 mb-4 sm:mb-6 w-fit min-w-0">
            @if($bookingLogoPath && file_exists(public_path($bookingLogoPath)))
                <img src="{{ asset($bookingLogoPath) }}"
                     width="88"
                     height="88"
                     alt="{{ $bookingName }}"
                     class="block h-14 sm:h-16 w-auto max-w-[72px] sm:max-w-[88px] object-contain shrink-0">
            @endif
            <span style="font-family: var(--font-display)" class="font-bold text-lg text-[#151515]">{{ $bookingName }}</span>
        </a>
        <h1 style="font-family: var(--font-display)" class="text-2xl sm:text-3xl font-bold text-[#151515] mb-2">{{ $registrationHeading }}</h1>
        <p class="text-[#bf4316] font-semibold mb-3">{{ $registrationWelcome }}</p>
        <p class="text-sm text-[#6a6e73] max-w-xl">{{ $registrationIntro }}</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-6 sm:gap-8">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="flex flex-col gap-5">
                <div class="flex items-center gap-3 mb-1">
                    <span class="w-8 h-8 rounded-full bg-[#bf4316] text-white flex items-center justify-center font-bold text-sm shrink-0">1</span>
                    <span style="font-family: var(--font-display)" class="font-bold text-lg text-[#151515]">{{ __('booking.register.account_section') }}</span>
                </div>

                <div class="ui-field">
                    <label class="ui-label flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-[#6a6e73]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        {{ __('booking.register.email') }}
                    </label>
                    <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" class="ui-input {{ $errors->has('email') ? 'border-red-400' : '' }}">
                    <p class="ui-help">{{ $registrationEmailHelp }}</p>
                    @error('email')<p class="ui-error">{{ $message }}</p>@enderror
                </div>

                <div class="ui-field">
                    <label class="ui-label">{{ __('booking.register.email_confirm') }}</label>
                    <input type="email" name="email_confirm" value="{{ old('email_confirm') }}" autocomplete="off" class="ui-input {{ $errors->has('email_confirm') ? 'border-red-400' : '' }}">
                    <p class="ui-help">{{ __('booking.register.email_confirm_help') }}</p>
                    @error('email_confirm')<p class="ui-error">{{ $message }}</p>@enderror
                </div>

                <div class="ui-field">
                    <label class="ui-label flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-[#6a6e73]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        {{ __('booking.register.password') }}
                    </label>
                    <input type="password" name="password" autocomplete="new-password" class="ui-input {{ $errors->has('password') ? 'border-red-400' : '' }}">
                    <p class="ui-help">{{ __('booking.register.password_help') }}</p>
                    @error('password')<p class="ui-error">{{ $message }}</p>@enderror
                </div>

                <div class="ui-field">
                    <label class="ui-label">{{ __('booking.register.password_confirm') }}</label>
                    <input type="password" name="password_confirm" autocomplete="new-password" class="ui-input {{ $errors->has('password_confirm') ? 'border-red-400' : '' }}">
                    <p class="ui-help">{{ __('booking.register.password_confirm_help') }}</p>
                    @error('password_confirm')<p class="ui-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex flex-col gap-5">
                <div class="flex items-center gap-3 mb-1">
                    <span class="w-8 h-8 rounded-full bg-[#bf4316] text-white flex items-center justify-center font-bold text-sm shrink-0">2</span>
                    <span style="font-family: var(--font-display)" class="font-bold text-lg text-[#151515]">{{ __('booking.register.profile_section') }}</span>
                </div>

                <div class="ui-field">
                    <label class="ui-label">{{ __('booking.register.gender') }}</label>
                    <select name="gender" class="ui-select">
                        <option value="">{{ __('booking.register.gender_placeholder') }}</option>
                        <option value="m" @selected(old('gender') === 'm')>{{ __('booking.register.gender_male') }}</option>
                        <option value="f" @selected(old('gender') === 'f')>{{ __('booking.register.gender_female') }}</option>
                        <option value="d" @selected(old('gender') === 'd')>{{ __('booking.register.gender_diverse') }}</option>
                    </select>
                </div>

                <div class="ui-field">
                    <label class="ui-label">{{ __('booking.register.full_name') }} <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <input type="text" name="firstname" value="{{ old('firstname') }}" placeholder="{{ __('booking.register.firstname_placeholder') }}" class="ui-input {{ $errors->has('firstname') ? 'border-red-400' : '' }}">
                        <input type="text" name="lastname" value="{{ old('lastname') }}" placeholder="{{ __('booking.register.lastname_placeholder') }}" class="ui-input {{ $errors->has('lastname') ? 'border-red-400' : '' }}">
                    </div>
                    @error('firstname')<p class="ui-error">{{ $message }}</p>@enderror
                    @error('lastname')<p class="ui-error">{{ $message }}</p>@enderror
                </div>

                <div class="ui-field">
                    <label class="ui-label">{{ __('booking.register.street') }}</label>
                    <input type="text" name="street" value="{{ old('street') }}" class="ui-input {{ $errors->has('street') ? 'border-red-400' : '' }}">
                    @error('street')<p class="ui-error">{{ $message }}</p>@enderror
                </div>

                <div class="ui-field">
                    <label class="ui-label">{{ __('booking.register.zip_city') }}</label>
                    <div class="grid grid-cols-1 sm:grid-cols-[6rem_1fr] gap-2">
                        <input type="text" name="zip" value="{{ old('zip') }}" placeholder="{{ __('booking.register.zip_placeholder') }}" class="ui-input {{ $errors->has('zip') ? 'border-red-400' : '' }}">
                        <input type="text" name="city" value="{{ old('city') }}" placeholder="{{ __('booking.register.city_placeholder') }}" class="ui-input {{ $errors->has('city') ? 'border-red-400' : '' }}">
                    </div>
                    @error('zip')<p class="ui-error">{{ $message }}</p>@enderror
                    @error('city')<p class="ui-error">{{ $message }}</p>@enderror
                </div>

                <div class="ui-field">
                    <label class="ui-label">{{ __('booking.register.phone') }} <span class="text-red-500">*</span></label>
                    <input type="tel" name="phone" value="{{ old('phone') }}" class="ui-input {{ $errors->has('phone') ? 'border-red-400' : '' }}">
                    <p class="ui-help">{{ __('booking.register.phone_help') }}</p>
                    @error('phone')<p class="ui-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="border-t border-[#e0ddd7] pt-6 flex flex-col items-stretch sm:items-center gap-4">
            <label class="flex items-start gap-2 text-sm text-[#151515] cursor-pointer">
                <input type="checkbox" name="privacy" value="1" class="{{ $errors->has('privacy') ? 'outline outline-red-400' : '' }}">
                {!! $registrationPrivacy !!}
            </label>
            @error('privacy')<p class="ui-error text-center">{{ $message }}</p>@enderror

            <button type="submit" class="ui-btn ui-btn-primary w-full sm:w-auto px-10">{{ __('booking.register.submit') }}</button>

            <p class="text-sm text-[#6a6e73] text-center">
                {{ __('booking.register.already_registered') }}
                <a href="{{ route('login') }}" class="text-[#bf4316] hover:underline">{{ __('booking.nav.login') }}</a>
            </p>
        </div>
    </form>
</div>

</body>
</html>

