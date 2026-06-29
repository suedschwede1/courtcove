@extends('layouts.app')
@section('title', __('booking.account.my_account'))

@section('content')
<div class="max-w-2xl mx-auto px-4 py-8 flex flex-col gap-6">

    <h1 class="text-2xl font-bold text-[#151515]"
        style="font-family: var(--font-display)">{{ __('booking.account.my_account') }}</h1>

    {{-- Flash-Meldungen --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg px-4 py-3">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">
            {{ session('error') }}
        </div>
    @endif

    {{-- Card 1: Profil --}}
    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]"
                style="font-family: var(--font-display)">Profil</h2>
        </div>
        <form method="POST" action="{{ route('account.update') }}">
            @csrf
            @method('PUT')
            <div class="px-6 py-5 flex flex-col gap-4">

                {{-- Anzeigename (full-width, required) --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">
                        {{ __('booking.account.display_name') }}
                    </label>
                    <input type="text" name="alias"
                           value="{{ old('alias', $user->alias) }}"
                           required maxlength="128"
                           class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent {{ $errors->has('alias') ? 'border-red-400' : 'border-[#d1cbc0]' }}">
                    @error('alias')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- 2-Spalten-Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">
                            {{ __('booking.account.firstname') }}
                        </label>
                        <input type="text" name="firstname"
                               value="{{ old('firstname', $profile['firstname']) }}"
                               maxlength="128"
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        @error('firstname')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">
                            {{ __('booking.account.lastname') }}
                        </label>
                        <input type="text" name="lastname"
                               value="{{ old('lastname', $profile['lastname']) }}"
                               maxlength="128"
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        @error('lastname')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">
                            {{ __('booking.account.email') }}
                        </label>
                        <input type="email" name="email"
                               value="{{ old('email', $user->email) }}"
                               maxlength="128"
                               class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent {{ $errors->has('email') ? 'border-red-400' : 'border-[#d1cbc0]' }}">
                        @error('email')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">
                            {{ __('booking.account.phone') }}
                        </label>
                        <input type="text" name="phone"
                               value="{{ old('phone', $profile['phone']) }}"
                               maxlength="128"
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        @error('phone')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">
                            {{ __('booking.account.street') }}
                        </label>
                        <input type="text" name="street"
                               value="{{ old('street', $profile['street']) }}"
                               maxlength="128"
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        @error('street')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">
                            {{ __('booking.account.zip') }}
                        </label>
                        <input type="text" name="zip"
                               value="{{ old('zip', $profile['zip']) }}"
                               maxlength="128"
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        @error('zip')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">
                            {{ __('booking.account.city') }}
                        </label>
                        <input type="text" name="city"
                               value="{{ old('city', $profile['city']) }}"
                               maxlength="128"
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        @error('city')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">
                            {{ __('booking.account.gender') }}
                        </label>
                        <select name="gender"
                                class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                            @php($gender = old('gender', $profile['gender']))
                            <option value="" @selected($gender === null || $gender === '')>–</option>
                            <option value="male" @selected($gender === 'male')>{{ __('booking.account.male') }}</option>
                            <option value="female" @selected($gender === 'female')>{{ __('booking.account.female') }}</option>
                        </select>
                    </div>

                </div>
            </div>
            <div class="px-6 pb-5 flex justify-end">
                <button type="submit"
                        class="bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium px-5 py-2 rounded transition-colors">
                    {{ __('booking.admin.save') }}
                </button>
            </div>
        </form>
    </div>

    {{-- Card 2: Passwort ändern --}}
    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]"
                style="font-family: var(--font-display)">{{ __('booking.account.password_change') }}</h2>
        </div>
        <form method="POST" action="{{ route('account.password') }}">
            @csrf
            @method('PUT')
            <div class="px-6 py-5 flex flex-col gap-4">

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">
                        {{ __('booking.account.current_password') }}
                    </label>
                    <input type="password" name="current_password"
                           required
                           class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent {{ $errors->has('current_password') ? 'border-red-400' : 'border-[#d1cbc0]' }}">
                    @error('current_password')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">
                        {{ __('booking.account.new_password') }}
                    </label>
                    <input type="password" name="password"
                           required
                           class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent {{ $errors->has('password') ? 'border-red-400' : 'border-[#d1cbc0]' }}">
                    @error('password')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">
                        {{ __('booking.account.new_password_confirmation') }}
                    </label>
                    <input type="password" name="password_confirmation"
                           required
                           class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>

            </div>
            <div class="px-6 pb-5 flex justify-end">
                <button type="submit"
                        class="bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium px-5 py-2 rounded transition-colors">
                    {{ __('booking.account.password_change') }}
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
