@props(['title', 'intro' => null, 'footer' => null, 'introDesktopOnly' => false])
@php
    $hasFooter = $footer !== null && trim((string) $footer) !== '';
@endphp
<div class="ds-auth">
    <div class="ds-auth__panel">
        <div class="ds-auth__lines" aria-hidden="true">
            <span class="ds-auth__line ds-auth__line--outer"></span>
            <span class="ds-auth__line ds-auth__line--singles"></span>
            <span class="ds-auth__line ds-auth__line--net"></span>
            <span class="ds-auth__line ds-auth__line--service"></span>
            <span class="ds-auth__line ds-auth__line--center"></span>
        </div>
        <p class="ds-auth__brand">
            <x-ui.logo-mark :name="$bookingName" />
            <span class="ds-auth__club">{{ $bookingName }}</span>
        </p>
        <div class="ds-auth__claim">
            <p class="ds-auth__tagline"><span>{{ __('booking.auth.panel.line1') }}</span> <span>{{ __('booking.auth.panel.line2') }}</span></p>
            <p class="ds-auth__panel-text">{{ __('booking.auth.panel.text') }}</p>
        </div>
    </div>
    <main id="content" class="ds-auth__main">
        <div class="ds-auth__column">
            <div class="ds-auth__top">
                <a class="ds-auth__back" href="{{ route('calendar.index') }}"><x-ui.icon name="chevron-left" size="16" /><span>{{ __('booking.auth.back_to_calendar') }}</span></a>
                <x-layout.locale-switch />
            </div>
            <div class="ds-auth__head">
                <h1 class="ds-auth__title">{{ $title }}</h1>
                @if($intro !== null && trim((string) $intro) !== '')
                    <p class="ds-auth__intro{{ $introDesktopOnly ? ' ds-auth__intro--desktop' : '' }}">{{ $intro }}</p>
                @endif
            </div>
            {{ $slot }}
            @if($hasFooter)
                <div class="ds-auth__footer">{{ $footer }}</div>
            @endif
            <p class="ds-auth__powered">{{ __('booking.auth.powered_by', ['product' => config('booking.product_name')]) }}</p>
        </div>
    </main>
</div>
