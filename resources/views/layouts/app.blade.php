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
            <div x-data="{ open: true, timer: null }" x-init="timer = setTimeout(() => open = false, 4000)" @pointerdown="clearTimeout(timer)" @keydown="clearTimeout(timer)">
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
