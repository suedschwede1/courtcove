@extends('layouts.base')

@section('title', config('app.name'))

@section('body-class', 'ui-admin-popup')

@section('body')
    <div class="ui-popup-shell">
        <div class="ui-popup-header">
            <h1>@yield('title', config('app.name'))</h1>
            <button type="button" class="text-[#8a8d90] text-xl leading-none" onclick="window.parent?.postMessage({ type: 'booking-modal-close' }, '*'); window.close();">&times;</button>
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
