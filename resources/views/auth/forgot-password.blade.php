@extends('layouts.base')

@section('title')
    {{ str_replace(':system', $bookingName, __('booking.password_reset.forgot_title')) }}
@endsection

@section('body-class', 'min-h-screen bg-[#f0ede6] flex flex-col')

@section('body')
    <header class="bg-white border-b border-[#e0ddd7] px-4 sm:px-6 py-3 sm:h-14 flex items-center gap-3">
        @if($bookingLogoPath && file_exists(public_path($bookingLogoPath)))
            <img src="{{ asset($bookingLogoPath) }}" alt="{{ $bookingName }}" class="h-9 w-auto object-contain">
        @endif
        <span class="font-semibold text-[#151515] text-sm" style="font-family: var(--font-display)">{{ $bookingName }}</span>
    </header>

    <main class="flex-1 flex items-center justify-center p-4 sm:p-6">
        <div class="w-full max-w-md bg-white rounded-xl shadow-sm border border-[#e0ddd7] overflow-hidden">
            <div class="p-6 sm:p-10 flex flex-col gap-5">
                <h1 class="text-2xl font-bold text-[#151515] leading-tight" style="font-family: var(--font-display)">{{ __('booking.password_reset.forgot_heading') }}</h1>
                <p class="text-sm text-[#6a6e73] leading-relaxed">{{ __('booking.password_reset.forgot_intro') }}</p>

                @if(session('status'))
                    <div class="text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-4 py-3">
                        {{ session('status') }}
                    </div>
                @else
                    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
                        @csrf
                        <div class="flex flex-col gap-1">
                            <label for="email" class="text-sm font-medium text-[#151515]">{{ __('booking.auth.email') }}</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="{{ __('booking.password_reset.forgot_email_placeholder') }}" class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent {{ $errors->has('email') ? 'border-red-400' : 'border-[#cccccc]' }}">
                            @error('email')
                                <p class="text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="w-full bg-[#bf4316] hover:bg-[#9e3412] text-white font-medium py-2 px-4 rounded transition-colors mt-1">{{ __('booking.password_reset.forgot_submit') }}</button>
                    </form>
                @endif

                <p class="mt-2 text-center text-xs text-[#6a6e73]">
                    <a href="{{ route('login') }}" class="text-[#bf4316] hover:underline">{{ __('booking.password_reset.back_to_login') }}</a>
                </p>
            </div>
        </div>
    </main>
@endsection
