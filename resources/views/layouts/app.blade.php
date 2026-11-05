@extends('layouts.base')

@push('head')
    <meta name="format-detection" content="telephone=no">
    <style>
        :root {
            @foreach(config('calendar.colors') as $group => $values)
                @foreach($values as $key => $value)
            --cal-{{ str_replace('_', '-', $group) }}-{{ str_replace('_', '-', $key) }}: {{ $value }};
                @endforeach
            @endforeach
        }
    </style>
@endpush

@section('body')
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
@endsection
