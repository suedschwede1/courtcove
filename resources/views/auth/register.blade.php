<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrierung – {{ config('booking.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Red+Hat+Display:wght@600;700&family=Red+Hat+Text:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/booking.css') }}">
</head>
<body style="font-family: var(--font-body); background: var(--ui-bg); min-height: 100vh;">

<div class="max-w-3xl mx-auto px-4 py-10">

    {{-- Header --}}
    <div class="mb-8">
        <a href="{{ route('calendar.index') }}" class="flex items-center gap-3 mb-6 w-fit">
            <img src="{{ asset(config('booking.logo_path')) }}"
                 width="{{ config('booking.logo_width') }}"
                 height="{{ config('booking.logo_height') }}"
                 alt="{{ config('booking.name') }}"
                 class="block">
            <span style="font-family: var(--font-display)" class="font-bold text-lg text-[#151515]">{{ config('booking.name') }}</span>
        </a>
        <h1 style="font-family: var(--font-display)" class="text-3xl font-bold text-[#151515] mb-2">Registrierung</h1>
        <p class="text-[#bf4316] font-semibold mb-3">Willkommen zu unserem {{ $bookingName }}</p>
        <p class="text-sm text-[#6a6e73] max-w-xl">
            Die Online-Platzbuchung steht ausschließlich Vereinsmitgliedern zur Verfügung.
            Um freie Plätze buchen zu können, erstellen Sie bitte ein Benutzerkonto auf Ihren Namen und Ihre E-Mail-Adresse.
        </p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-8">
        @csrf

        {{-- 2-Spalten-Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

            {{-- Spalte 1: Zugangsdaten --}}
            <div class="flex flex-col gap-5">
                <div class="flex items-center gap-3 mb-1">
                    <span class="w-8 h-8 rounded-full bg-[#bf4316] text-white flex items-center justify-center font-bold text-sm shrink-0">1</span>
                    <span style="font-family: var(--font-display)" class="font-bold text-lg text-[#151515]">Zugangsdaten</span>
                </div>

                {{-- E-Mail --}}
                <div class="ui-field">
                    <label class="ui-label flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-[#6a6e73]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        E-Mail Adresse
                    </label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           autocomplete="email"
                           class="ui-input {{ $errors->has('email') ? 'border-red-400' : '' }}">
                    <p class="ui-help">Hiermit melden Sie sich an</p>
                    @error('email')<p class="ui-error">{{ $message }}</p>@enderror
                </div>

                <div class="ui-field">
                    <label class="ui-label">E-Mail bestätigen</label>
                    <input type="email" name="email_confirm" value="{{ old('email_confirm') }}"
                           autocomplete="off"
                           class="ui-input {{ $errors->has('email_confirm') ? 'border-red-400' : '' }}">
                    <p class="ui-help">Bitte geben Sie Ihre E-Mail Adresse zum Schutz gegen Tippfehler noch einmal ein</p>
                    @error('email_confirm')<p class="ui-error">{{ $message }}</p>@enderror
                </div>

                {{-- Passwort --}}
                <div class="ui-field">
                    <label class="ui-label flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-[#6a6e73]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Passwort
                    </label>
                    <input type="password" name="password"
                           autocomplete="new-password"
                           class="ui-input {{ $errors->has('password') ? 'border-red-400' : '' }}">
                    <p class="ui-help">Ihr Passwort wird sicher verschlüsselt (min. 8 Zeichen)</p>
                    @error('password')<p class="ui-error">{{ $message }}</p>@enderror
                </div>

                <div class="ui-field">
                    <label class="ui-label">Passwort bestätigen</label>
                    <input type="password" name="password_confirm"
                           autocomplete="new-password"
                           class="ui-input {{ $errors->has('password_confirm') ? 'border-red-400' : '' }}">
                    <p class="ui-help">Bitte geben Sie Ihr Passwort zum Schutz gegen Tippfehler noch einmal ein</p>
                    @error('password_confirm')<p class="ui-error">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Spalte 2: Persönliche Angaben --}}
            <div class="flex flex-col gap-5">
                <div class="flex items-center gap-3 mb-1">
                    <span class="w-8 h-8 rounded-full bg-[#bf4316] text-white flex items-center justify-center font-bold text-sm shrink-0">2</span>
                    <span style="font-family: var(--font-display)" class="font-bold text-lg text-[#151515]">Persönliche Angaben</span>
                </div>

                <div class="ui-field">
                    <label class="ui-label">Anrede</label>
                    <select name="gender" class="ui-select">
                        <option value="">– bitte wählen –</option>
                        <option value="m" @selected(old('gender') === 'm')>Herr</option>
                        <option value="f" @selected(old('gender') === 'f')>Frau</option>
                        <option value="d" @selected(old('gender') === 'd')>Divers</option>
                    </select>
                </div>

                <div class="ui-field">
                    <label class="ui-label">Vor- &amp; Nachname <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="firstname" value="{{ old('firstname') }}"
                               placeholder="Vorname"
                               class="ui-input {{ $errors->has('firstname') ? 'border-red-400' : '' }}">
                        <input type="text" name="lastname" value="{{ old('lastname') }}"
                               placeholder="Nachname"
                               class="ui-input {{ $errors->has('lastname') ? 'border-red-400' : '' }}">
                    </div>
                    @error('firstname')<p class="ui-error">{{ $message }}</p>@enderror
                    @error('lastname')<p class="ui-error">{{ $message }}</p>@enderror
                </div>

                <div class="ui-field">
                    <label class="ui-label">Straße und Hausnummer</label>
                    <input type="text" name="street" value="{{ old('street') }}"
                           class="ui-input {{ $errors->has('street') ? 'border-red-400' : '' }}">
                    @error('street')<p class="ui-error">{{ $message }}</p>@enderror
                </div>

                <div class="ui-field">
                    <label class="ui-label">Postleitzahl &amp; Ort</label>
                    <div class="grid grid-cols-[6rem_1fr] gap-2">
                        <input type="text" name="zip" value="{{ old('zip') }}"
                               placeholder="PLZ"
                               class="ui-input {{ $errors->has('zip') ? 'border-red-400' : '' }}">
                        <input type="text" name="city" value="{{ old('city') }}"
                               placeholder="Ort"
                               class="ui-input {{ $errors->has('city') ? 'border-red-400' : '' }}">
                    </div>
                    @error('zip')<p class="ui-error">{{ $message }}</p>@enderror
                    @error('city')<p class="ui-error">{{ $message }}</p>@enderror
                </div>

                <div class="ui-field">
                    <label class="ui-label">Telefonnummer <span class="text-red-500">*</span></label>
                    <input type="tel" name="phone" value="{{ old('phone') }}"
                           class="ui-input {{ $errors->has('phone') ? 'border-red-400' : '' }}">
                    <p class="ui-help">Wird benötigt, damit wir Sie bei Buchungsänderungen informieren können</p>
                    @error('phone')<p class="ui-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Datenschutz + Submit --}}
        <div class="border-t border-[#e0ddd7] pt-6 flex flex-col items-center gap-4">
            <label class="flex items-center gap-2 text-sm text-[#151515] cursor-pointer">
                <input type="checkbox" name="privacy" value="1" class="{{ $errors->has('privacy') ? 'outline outline-red-400' : '' }}">
                Ich habe die <a href="#" class="text-[#bf4316] underline">Datenschutzerklärung</a> gelesen und akzeptiere diese
            </label>
            @error('privacy')<p class="ui-error text-center">{{ $message }}</p>@enderror

            <button type="submit" class="ui-btn ui-btn-primary px-10">
                Registrierung abschließen
            </button>

            <p class="text-sm text-[#6a6e73]">
                Bereits registriert?
                <a href="{{ route('login') }}" class="text-[#bf4316] hover:underline">Anmelden</a>
            </p>
        </div>
    </form>
</div>

</body>
</html>
