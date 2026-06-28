<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <title>@yield('title', config('booking.name'))</title>
    <link rel="stylesheet" href="{{ asset('css/booking.css') }}">
    @stack('head')
</head>
<body>
<div class="page-shell">
    <header class="top-header no-print">
        <div class="brand-card">
            <div class="brand-row">
                <a href="{{ route('calendar.index') }}" class="brand-logo-link" aria-label="{{ config('booking.name') }}" style="--booking-logo-width: {{ config('booking.logo_width') }}px; --booking-logo-height: {{ config('booking.logo_height') }}px;">
                    <img src="{{ asset(config('booking.logo_path')) }}"
                         width="{{ config('booking.logo_width') }}"
                         height="{{ config('booking.logo_height') }}"
                         alt="{{ config('booking.name') }}"
                         class="brand-logo-image">
                </a>
                <div class="brand-copy">
                    <div class="brand-title">{{ config('booking.name') }}</div>
                    <div class="brand-toolbar">
                        @stack('header-nav')
                    </div>
                </div>
            </div>
        </div>

        <div class="header-actions-card">
            <div class="header-actions">
                @hasSection('calendar-system-info')
                    <button type="button" class="default-button header-help-toggle" data-panel-toggle="system-info-panel">Infos</button>
                @endif
                @hasSection('calendar-help')
                    <button type="button" class="default-button header-help-toggle" data-panel-toggle="help-panel">Hinweise</button>
                @endif
                @auth
                    <a href="{{ route('account.bookings') }}" class="default-button">Meine Buchungen</a>
                    <a href="{{ route('account.edit') }}" class="default-button">Mein Konto</a>
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
        @if(session('success') || session('error'))
            <div id="feedback-modal" class="booking-modal" style="display:block;">
                <div class="booking-modal__viewport">
                    <div class="booking-modal__card booking-modal__card--feedback">
                        <button id="feedback-modal-close" class="booking-modal__close" title="Schließen">&#x2715;</button>
                        <div class="booking-modal__header">
                            <h2>{{ session('success') ? 'Erfolg' : 'Hinweis' }}</h2>
                        </div>
                        <div class="booking-modal__body">
                            <p class="{{ session('success') ? 'booking-modal__success' : 'booking-modal__warning' }} booking-modal__flash">{{ session('success') ?? session('error') }}</p>
                        </div>
                        <div class="booking-modal__actions">
                            <button type="button" id="feedback-modal-ok" class="default-button">Schließen</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @hasSection('calendar-system-info')
            <section class="help-panel no-print" id="system-info-panel" hidden>
                @yield('calendar-system-info')
            </section>
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

