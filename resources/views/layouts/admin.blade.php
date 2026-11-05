@extends('layouts.base')

@section('layout-title')
    {{ __('booking.nav.admin') }} - @yield('admin-title', __('booking.admin.overview'))
@endsection

@section('body-class', request('popup') ? 'admin-popup-mode' : '')

@section('body')
<div class="ds-admin" x-data="{ navOpen: false }" x-on:keydown.escape.window="if (navOpen) { navOpen = false; $refs.navButton?.focus() }" x-on:resize.window="if (navOpen && window.innerWidth >= 768) navOpen = false" x-effect="document.documentElement.classList.toggle('ds-scroll-lock', navOpen)">
    @unless(request('popup'))
        <x-layout.admin-sidebar />

        <div class="ds-admin-backdrop" x-cloak x-show="navOpen" x-on:click="navOpen = false"></div>

        <header class="ds-admin-bar ds-on-chrome">
            @hasSection('admin-back')
                <x-ui.icon-button icon="chevron-left" variant="chrome" class="ds-admin-bar__lead" :href="htmlspecialchars_decode(trim($__env->yieldContent('admin-back')), ENT_QUOTES)" :label="__('booking.admin.nav.back')" />
            @else
                <x-ui.icon-button icon="menu" variant="chrome" class="ds-admin-bar__lead" :label="__('booking.admin.nav.open')" x-ref="navButton" x-on:click="navOpen = true; $nextTick(() => $refs.navClose?.focus())" aria-controls="admin-nav" aria-expanded="false" x-bind:aria-expanded="navOpen ? 'true' : 'false'" />
            @endif
            <div class="ds-admin-bar__text">
                <p class="ds-admin-bar__eyebrow">{{ __('booking.admin.nav.eyebrow') }}</p>
                <p class="ds-admin-bar__title">@yield('admin-title', __('booking.admin.overview'))</p>
            </div>
            <x-ui.icon-button icon="calendar" variant="chrome" :href="route('calendar.index')" :label="__('booking.admin.to_calendar')" />
        </header>
    @endunless

    <main id="content" class="ds-admin-main">
        @if(session('success'))
            <x-ui.notice tone="good">{{ session('success') }}</x-ui.notice>
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
