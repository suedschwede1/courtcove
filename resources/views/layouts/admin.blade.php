{{--
    The area shell: left sidebar (x-layout.admin-sidebar, slide-in with the phone bar's menu button below 48rem) and
    the content column. One shell for every area: administration (default sidebar), Members (members::layout) and
    the tournament modules (tournament-base::layouts.area). An area fills admin-sidebar with its navigation provider.
    Sections: admin-title, admin-eyebrow, admin-back (phone bar back arrow instead of the menu), phone-bar (a page's
    own x-layout.phone-bar instead of the shell's, carrying x-layout.area-menu-button), admin-content.
--}}
@extends('layouts.base')

@section('layout-title')
    {{ __('booking.nav.admin') }} - @yield('admin-title', __('booking.admin.overview'))
@endsection

@section('body-class', request('popup') ? 'admin-popup-mode' : '')

@section('body')
<div class="ds-admin" x-data="{ navOpen: false }" x-on:keydown.escape.window="if (navOpen) { navOpen = false; $nextTick(() => $refs.navButton?.focus()) }" x-on:resize.window="if (navOpen && window.innerWidth >= 768) navOpen = false" x-effect="document.documentElement.classList.toggle('ds-scroll-lock', navOpen)">
    @unless(request('popup'))
        {{-- An area with its own sidebar navigation (Members) fills admin-sidebar. --}}
        @hasSection('admin-sidebar')
            @yield('admin-sidebar')
        @else
            <x-layout.admin-sidebar />
        @endif

        <div class="ds-admin-backdrop" x-cloak x-show="navOpen" x-on:click="navOpen = false"></div>

        @hasSection('phone-bar')
            @yield('phone-bar')
        @else
        <header class="ds-admin-bar ds-on-chrome" x-bind:inert="navOpen">
            @hasSection('admin-back')
                <x-ui.icon-button icon="chevron-left" variant="chrome" class="ds-admin-bar__lead" :href="htmlspecialchars_decode(trim($__env->yieldContent('admin-back')), ENT_QUOTES)" :label="__('booking.admin.nav.back')" />
            @else
                <x-layout.area-menu-button class="ds-admin-bar__lead" />
            @endif
            <div class="ds-admin-bar__text">
                <p class="ds-admin-bar__eyebrow">@yield('admin-eyebrow', __('booking.admin.nav.eyebrow'))</p>
                <p class="ds-admin-bar__title">@yield('admin-title', __('booking.admin.overview'))</p>
            </div>
            @auth
                <x-ui.icon-button icon="calendar" variant="chrome" :href="route('calendar.index')" :label="__('booking.admin.to_calendar')" />
            @else
                <x-ui.icon-button icon="user" variant="chrome" :href="route('login', ['redirect_to' => url()->full()])" :label="__('booking.nav.login')" />
            @endauth
        </header>
        @endif
    @endunless

    <main id="content" class="ds-admin-main" x-bind:inert="navOpen">
        @if(session('success'))
            <x-ui.notice tone="good">{{ session('success') }}</x-ui.notice>
        @endif
        @if(session('info'))
            <x-ui.notice tone="neutral">{{ session('info') }}</x-ui.notice>
        @endif
        @if(session('error'))
            <x-ui.notice tone="problem">{{ session('error') }}</x-ui.notice>
        @endif
        @if($errors->any())
            <x-ui.notice tone="problem">{{ $errors->first() }}</x-ui.notice>
        @endif
        @yield('admin-content')
    </main>
</div>
@endsection
