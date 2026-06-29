@php
    $bookingName = trim((string) \App\Models\Option::getValue('service.name', config('booking.name')));
    $bookingName = $bookingName !== '' ? $bookingName : config('booking.name');
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <title>@yield('title', $bookingName)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Red+Hat+Display:wght@600;700&family=Red+Hat+Text:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
    @stack('head')
</head>
<body>
<div class="page-shell">
    <header class="top-header no-print">
        <div class="brand-card">
            <div class="brand-row">
                <a href="{{ route('calendar.index') }}" class="brand-logo-link" aria-label="{{ $bookingName }}" style="--booking-logo-width: {{ config('booking.logo_width') }}px; --booking-logo-height: {{ config('booking.logo_height') }}px;">
                    <img src="{{ asset(config('booking.logo_path')) }}"
                         width="{{ config('booking.logo_width') }}"
                         height="{{ config('booking.logo_height') }}"
                         alt="{{ $bookingName }}"
                         class="brand-logo-image">
                </a>
                <div class="brand-copy">
                    <div class="brand-title">{{ $bookingName }}</div>
                    <div class="brand-toolbar">
                        @stack('header-nav')
                    </div>
                </div>
            </div>
        </div>

        <div class="header-actions-card">
            <div class="header-actions">
                @hasSection('calendar-system-info')
                    <button type="button" class="default-button header-help-toggle" data-panel-toggle="system-info-panel">{{ __('booking.nav.info') }}</button>
                @endif
                @hasSection('calendar-help')
                    <button type="button" class="default-button header-help-toggle" data-panel-toggle="help-panel">{{ __('booking.nav.help') }}</button>
                @endif
                @auth
                    <a href="{{ route('account.bookings') }}" class="default-button">{{ __('booking.nav.my_bookings') }}</a>
                    <a href="{{ route('account.edit') }}" class="default-button">{{ __('booking.nav.my_account') }}</a>
                    @can('admin.see-menu')
                        <a href="{{ route('admin.dashboard') }}" class="default-button">{{ __('booking.nav.admin') }}</a>
                    @endcan
                    <form method="POST" action="{{ route('logout') }}" style="display:inline; margin:0;">
                        @csrf
                        <button type="submit" class="default-button abmelden-button">{{ __('booking.nav.logout') }}</button>
                    </form>
                    <a href="#" class="default-button header-help">?</a>
                @else
                    <a href="{{ route('login', ['redirect_to' => url()->full()]) }}" class="default-button">{{ __('booking.nav.login') }}</a>
                @endauth
            </div>
        </div>
    </header>

    <main id="content">
        @if(session('success') || session('error'))
            <div id="feedback-modal" class="booking-modal" style="display:block;">
                <div class="booking-modal__viewport">
                    <div class="booking-modal__card booking-modal__card--feedback">
                        <button id="feedback-modal-close" class="booking-modal__close" title="{{ __('booking.feedback.close') }}">&#x2715;</button>
                        <div class="booking-modal__header">
                            <h2>{{ session('success') ? __('booking.feedback.success') : __('booking.feedback.notice') }}</h2>
                        </div>
                        <div class="booking-modal__body">
                            <p class="{{ session('success') ? 'booking-modal__success' : 'booking-modal__warning' }} booking-modal__flash">{{ session('success') ?? session('error') }}</p>
                        </div>
                        <div class="booking-modal__actions">
                            <button type="button" id="feedback-modal-ok" class="default-button">{{ __('booking.feedback.close') }}</button>
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

<script src="{{ asset('js/booking.js') }}?v={{ filemtime(public_path('js/booking.js')) }}"></script>
@stack('scripts')
</body>
</html>

