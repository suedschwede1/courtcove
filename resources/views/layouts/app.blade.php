@extends('layouts.base')

@push('head')
    <meta name="format-detection" content="telephone=no">
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
@endpush

@section('body')
<div class="page-shell">
    <x-layout.header />

    <main id="content">
        @if(session('success') || session('error'))
            <div x-data="{ open: true, timer: null }" x-init="timer = setTimeout(() => open = false, 4000)" @pointerenter="clearTimeout(timer)" @focusin="clearTimeout(timer)">
                <x-ui.modal id="feedback-modal" show="open">
                    <x-slot:title>{{ session('success') ? __('booking.feedback.success') : __('booking.feedback.notice') }}</x-slot:title>
                    @if(session('success'))
                        <x-ui.notice tone="good">{{ session('success') }}</x-ui.notice>
                    @else
                        <x-ui.notice tone="problem">{{ session('error') }}</x-ui.notice>
                    @endif
                    @if(session('booking_quote'))
                        <p class="ds-modal__quote">{{ session('booking_quote') }}</p>
                    @endif
                    <x-slot:footer>
                        <x-ui.button :block="false" x-on:click="open = false" data-modal-initial>{{ __('booking.feedback.close') }}</x-ui.button>
                    </x-slot:footer>
                </x-ui.modal>
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
@endsection
