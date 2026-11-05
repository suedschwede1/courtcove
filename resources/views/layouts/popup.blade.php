@extends('layouts.base')

@section('title', config('app.name'))

@section('body-class', 'admin-popup-mode')

@section('body')
<div class="ds-popup">
    <header class="ds-popup__head ds-on-chrome">
        <h1 class="ds-popup__title">@yield('title', config('app.name'))</h1>
        <x-ui.icon-button icon="close" variant="chrome" :label="__('booking.admin.common.close')" x-data x-on:click="window.parent?.postMessage({ type: 'booking-modal-close' }, '*'); window.close()" />
    </header>

    <main id="content" class="ds-popup__body">
        @if(session('success'))
            <x-ui.notice tone="good">{{ session('success') }}</x-ui.notice>
        @endif
        @if(session('error'))
            <x-ui.notice tone="problem">{{ session('error') }}</x-ui.notice>
        @endif
        @if($errors->any())
            <x-ui.notice tone="problem">{{ $errors->first() }}</x-ui.notice>
        @endif
        @hasSection('content')
            @yield('content')
        @else
            @yield('admin-content')
        @endif
    </main>
</div>
@endsection
