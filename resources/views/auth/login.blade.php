<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ str_replace(':system', $bookingName, __('booking.auth.title')) }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Red+Hat+Display:wght@600;700&family=Red+Hat+Text:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/booking.css') }}">
</head>
<body class="min-h-screen bg-[#f0ede6] flex flex-col" style="font-family: var(--font-body)">
    <header class="bg-white border-b border-[#e0ddd7] px-4 sm:px-6 py-3 sm:h-14 flex items-center gap-3">
        @if(config('booking.logo_path') && file_exists(public_path(config('booking.logo_path'))))
            <img src="{{ asset(config('booking.logo_path')) }}" alt="{{ $bookingName }}" class="h-9 w-auto object-contain">
        @endif
        <span class="font-semibold text-[#151515] text-sm" style="font-family: var(--font-display)">{{ $bookingName }}</span>
    </header>

    @if(session('registered'))
        <div class="mx-auto mt-6 max-w-2xl px-4">
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ str_replace(':system', $bookingName, $registrationContent['success']) }}
            </div>
        </div>
    @endif

    <main class="flex-1 flex items-center justify-center p-4 sm:p-6">
        <div class="w-full max-w-2xl bg-white rounded-xl shadow-sm border border-[#e0ddd7] overflow-hidden grid grid-cols-1 md:grid-cols-2">
            <div class="p-6 sm:p-10 flex flex-col justify-center gap-4 border-b md:border-b-0 md:border-r border-[#f0ede6]">
                <p class="text-xs font-semibold uppercase tracking-widest text-[#6a6e73]">{{ __('booking.auth.eyebrow') }}</p>
                <h1 class="text-2xl sm:text-3xl font-bold text-[#151515] leading-tight" style="font-family: var(--font-display)">{{ __('booking.auth.heading') }}</h1>
                <p class="text-sm text-[#6a6e73] leading-relaxed">{{ __('booking.auth.intro') }}</p>
            </div>

            <div class="p-6 sm:p-10 bg-[#fafaf9] flex flex-col gap-5">
                <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
                    @csrf
                    @if(request('redirect_to'))
                        <input type="hidden" name="redirect_to" value="{{ request('redirect_to') }}">
                    @endif

                    <div class="flex flex-col gap-1">
                        <label for="email" class="text-sm font-medium text-[#151515]">{{ __('booking.auth.email') }}</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="{{ __('booking.auth.email_placeholder') }}" class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent {{ $errors->has('email') ? 'border-red-400' : 'border-[#cccccc]' }}">
                        @error('email')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="password" class="text-sm font-medium text-[#151515]">{{ __('booking.auth.password') }}</label>
                        <input type="password" id="password" name="password" required autocomplete="current-password" class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent {{ $errors->has('password') ? 'border-red-400' : 'border-[#cccccc]' }}">
                        @error('password')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="remember" name="remember" class="accent-[#bf4316] w-4 h-4 cursor-pointer">
                        <label for="remember" class="text-sm text-[#151515] cursor-pointer">{{ __('booking.auth.remember') }}</label>
                    </div>

                    @if($errors->any() && !$errors->has('alias') && !$errors->has('password'))
                        <div class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <button type="submit" class="w-full bg-[#bf4316] hover:bg-[#9e3412] text-white font-medium py-2 px-4 rounded transition-colors mt-1">{{ __('booking.auth.submit') }}</button>
                </form>

                <p class="mt-2 text-center text-xs text-[#6a6e73]">
                    {{ __('booking.auth.no_account') }}
                    <a href="{{ route('register') }}" class="text-[#bf4316] hover:underline">{{ __('booking.auth.register_now') }}</a>
                </p>
            </div>
        </div>
    </main>

</body>
</html>

