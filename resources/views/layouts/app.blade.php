<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <title>@yield('title', config('booking.name'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Red+Hat+Display:wght@600;700&family=Red+Hat+Text:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
    <style>
        :root {
            --cal-free-bg: {{ config('calendar.colors.free.bg') }};
            --cal-free-bg-hover: {{ config('calendar.colors.free.bg_hover') }};
            --cal-own-bg: {{ config('calendar.colors.own.bg') }};
            --cal-own-bg-hover: {{ config('calendar.colors.own.bg_hover') }};
            --cal-own-accent: {{ config('calendar.colors.own.accent') }};
            --cal-own-label: {{ config('calendar.colors.own.label') }};
            --cal-own-label-secondary: {{ config('calendar.colors.own.label_secondary') }};
            --cal-single-future-bg: {{ config('calendar.colors.single_future.bg') }};
            --cal-single-future-bg-hover: {{ config('calendar.colors.single_future.bg_hover') }};
            --cal-series-accent: {{ config('calendar.colors.series.accent') }};
            --cal-series-label: {{ config('calendar.colors.series.label') }};
            --cal-past-bg: {{ config('calendar.colors.past.bg') }};
            --cal-past-label: {{ config('calendar.colors.past.label') }};
            --cal-event-bg: {{ config('calendar.colors.event.bg') }};
            --cal-event-bg-hover: {{ config('calendar.colors.event.bg_hover') }};
            --cal-event-accent: {{ config('calendar.colors.event.accent') }};
            --cal-event-label: {{ config('calendar.colors.event.label') }};
        }
    </style>
    @stack('head')
</head>
<body>
<div class="page-shell">
    <x-layout.header />

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
                            @if(session('booking_quote'))
                                <p class="booking-modal__quote">{{ session('booking_quote') }}</p>
                            @endif
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

