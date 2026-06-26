<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <title>@yield('title', config('app.name', 'ExampleClub-Booking'))</title>
    <link rel="stylesheet" href="{{ asset('css/booking.css') }}">
    @stack('head')
</head>
<body>
<div class="page-shell">
    <header class="top-header no-print">
        <div class="brand-card">
            <div class="brand-row">
                <a href="{{ route('calendar.index') }}" class="brand-logo-link" aria-label="{{ config('app.name', 'ExampleClub-Booking') }}">
                    <img src="{{ asset('imgs-client/layout/logo2.png') }}" width="120" style="display:block; height:auto;" alt="{{ config('app.name', 'ExampleClub-Booking') }}" class="brand-logo-image">
                </a>
                <div class="brand-copy">
                    <div class="brand-title">{{ config('app.name', 'ExampleClub-Booking') }}</div>
                    <div class="brand-toolbar">
                        @stack('header-nav')
                    </div>
                </div>
            </div>
        </div>

        <div class="header-actions-card">
            <div class="header-actions">
                @hasSection('calendar-help')
                    <button type="button" class="default-button header-help-toggle" data-help-toggle>Hinweise</button>
                @endif
                @auth
                    <a href="#" class="default-button">Meine Buchungen</a>
                    <a href="#" class="default-button">Mein Konto</a>
                    @can('admin.see-menu')
                        <a href="{{ route('admin.dashboard') }}" class="default-button">Administration</a>
                    @endcan
                    <form method="POST" action="{{ route('logout') }}" style="display:inline; margin:0;">
                        @csrf
                        <button type="submit" class="default-button abmelden-button">Abmelden</button>
                    </form>
                    <a href="#" class="default-button header-help">?</a>
                @else
                    <a href="{{ route('login', ['redirect_to' => url()->full()]) }}" class="default-button">Anmelden</a>
                @endauth
            </div>
        </div>
    </header>

    <main id="content">
        @if(session('success'))
            <div class="success-message">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="error-message">{{ session('error') }}</div>
        @endif

        @hasSection('calendar-help')
            <section class="help-panel no-print" id="help-panel" hidden>
                @yield('calendar-help')
            </section>
        @endif

        @yield('content')
    </main>
</div>

<script src="{{ asset('js/booking.js') }}"></script>
@stack('scripts')
</body>
</html>

