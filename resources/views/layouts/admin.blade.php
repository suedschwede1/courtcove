@extends('layouts.base')

@section('layout-title')
    {{ __('booking.nav.admin') }} - @yield('admin-title', __('booking.admin.overview'))
@endsection

@section('body-class', request('popup') ? 'admin-popup-mode ui-admin-popup' : '')

@section('body')
@php
    $serviceName = trim((string) \App\Models\Option::getValue('service.name', config('booking.name')));
    $serviceName = $serviceName !== '' ? $serviceName : config('booking.name');
@endphp

<div class="flex min-h-screen bg-[#f3f1ec]">
    @unless(request('popup'))
        <x-layout.admin-sidebar />
    @endunless

    <div class="ui-admin-main">
        @unless(request('popup'))
            <header class="flex items-center justify-between border-b border-[#e7e3da] bg-white px-6 py-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[#6a6e73]">{{ __('booking.admin.common.administration') }}</p>
                    <h1 class="mt-1 text-lg font-bold text-[#151515]" style="font-family: var(--font-display)">{{ $serviceName }}</h1>
                </div>
                <div class="flex items-center gap-4 text-sm text-[#6a6e73]">
                    @auth
                        <span>{{ auth()->user()->alias }}</span>
                        <form method="POST" action="{{ route('logout') }}" class="m-0">
                            @csrf
                            <button type="submit" class="ui-btn ui-btn-outline">{{ __('booking.nav.logout') }}</button>
                        </form>
                    @endauth
                </div>
            </header>
        @endunless

        @if(session('success'))
            <div class="mx-6 mt-6 ui-flash ui-flash-success">{{ session('success') }}</div>
        @endif
        @if(session('info'))
            <div class="mx-6 mt-6 ui-flash ui-flash-success">{{ session('info') }}</div>
        @endif
        @if(session('error'))
            <div class="mx-6 mt-6 ui-flash ui-flash-error">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="mx-6 mt-6 ui-flash ui-flash-error">{{ $errors->first() }}</div>
        @endif
        <main class="flex-1 p-6">@yield('admin-content')</main>
    </div>
</div>
@endsection
