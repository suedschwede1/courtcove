<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ str_replace(':system', $bookingName, __('booking.auth.verify.title')) }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Red+Hat+Display:wght@600;700&family=Red+Hat+Text:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/booking.css') }}">
</head>
<body class="min-h-screen bg-[#f0ede6] flex flex-col" style="font-family: var(--font-body)">
    <header class="bg-white border-b border-[#e0ddd7] px-4 sm:px-6 py-3 sm:h-14 flex items-center gap-3">
        @if($bookingLogoPath && file_exists(public_path($bookingLogoPath)))
            <img src="{{ asset($bookingLogoPath) }}" alt="{{ $bookingName }}" class="h-9 w-auto object-contain">
        @endif
        <span class="font-semibold text-[#151515] text-sm" style="font-family: var(--font-display)">{{ $bookingName }}</span>
    </header>

    <main class="flex-1 flex items-center justify-center p-4 sm:p-6">
        <div class="w-full max-w-md bg-white rounded-xl shadow-sm border border-[#e0ddd7] overflow-hidden p-6 sm:p-10 flex flex-col gap-5">
            <h1 class="text-2xl font-bold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.auth.verify.heading') }}</h1>
            <p class="text-sm text-[#6a6e73] leading-relaxed">{{ __('booking.auth.verify.intro') }}</p>

            <form method="POST" action="{{ route('login.verify.submit') }}" class="flex flex-col gap-4">
                @csrf
                <div class="flex flex-col gap-1">
                    <label for="code" class="text-sm font-medium text-[#151515]">{{ __('booking.auth.verify.code_label') }}</label>
                    <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autofocus required class="w-full border rounded px-3 py-2 text-lg tracking-widest text-center focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent {{ $errors->has('code') ? 'border-red-400' : 'border-[#cccccc]' }}">
                    @error('code')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full bg-[#bf4316] hover:bg-[#9e3412] text-white font-medium py-2 px-4 rounded transition-colors">{{ __('booking.auth.verify.submit') }}</button>
            </form>

            <form method="POST" action="{{ route('login.verify.resend') }}">
                @csrf
                <button type="submit" class="w-full text-center text-xs text-[#bf4316] hover:underline">{{ __('booking.auth.verify.resend') }}</button>
            </form>
        </div>
    </main>
</body>
<!-- {{ config('booking.watermark') }} -->
</html>
