@extends('layouts.base')

@section('layout-title')
    {{ __('booking.nav.members') }} - @yield('admin-title', __('members::admin.dashboard.title'))
@endsection

@push('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('body')
<div class="mbr-shell" x-data="{ navOpen: false }" x-bind:class="{ 'mbr-shell--nav-open': navOpen }" @keydown.escape.window="navOpen = false" x-effect="document.body.classList.toggle('ds-scroll-lock', navOpen)">
    <div class="mbr-topbar ds-on-chrome">
        <x-ui.icon-button icon="menu" variant="chrome" :label="__('booking.nav.open_menu')" aria-controls="members-sidebar" x-bind:aria-expanded="navOpen ? 'true' : 'false'" x-ref="menuButton" x-on:click="navOpen = true; $nextTick(() => $refs.navClose.focus())" />
        <div class="mbr-topbar__text">
            <span class="mbr-topbar__eyebrow">{{ __('booking.nav.members') }}</span>
            <span class="mbr-topbar__title">@yield('admin-title', __('members::admin.dashboard.title'))</span>
        </div>
    </div>

    <x-members::sidebar />
    <div class="mbr-shell__backdrop" aria-hidden="true" @click="navOpen = false"></div>

    <div class="mbr-main">
        <main class="mbr-content">
            @if(session('success'))
                <x-ui.notice tone="good">{{ session('success') }}</x-ui.notice>
            @endif
            @if(session('status'))
                <x-ui.notice tone="good">{{ session('status') }}</x-ui.notice>
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
</div>
@endsection
