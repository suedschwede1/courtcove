@extends('layouts.base')

@section('title', config('app.name'))

@section('body-class', 'ui-admin-popup')

@section('body')
    <div class="ui-popup-shell">
        <div class="ui-popup-header">
            <h1>@yield('title', config('app.name'))</h1>
            <x-ui.icon-button icon="close" variant="text" :label="__('booking.modal.close')" onclick="window.parent?.postMessage({ type: 'popup-close' }, '*'); window.close();" />
        </div>

        <div class="ui-popup-body">
            @if(session('success'))
                <div class="mb-4 ui-flash ui-flash-success">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 ui-flash ui-flash-error">{{ $errors->first() }}</div>
            @endif
            @yield('content')
        </div>
    </div>
@endsection
