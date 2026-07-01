<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Red+Hat+Display:wght@600;700&family=Red+Hat+Text:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/booking.css') }}">
</head>
<body class="ui-admin-popup" style="font-family: var(--font-body)">
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
</body>
</html>
