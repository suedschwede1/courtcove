<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('booking.nav.admin') }} - @yield('admin-title', __('booking.admin.overview'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Red+Hat+Display:wght@600;700&family=Red+Hat+Text:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/booking.css') }}">
</head>
<body class="{{ request('popup') ? 'admin-popup-mode ui-admin-popup' : '' }}" style="font-family: var(--font-body)">

<div class="flex min-h-screen">
    @unless(request('popup'))
        <x-layout.admin-sidebar />
    @endunless

    <div class="ui-admin-main">
        @if(session('success'))
            <div class="mx-6 mt-6 ui-flash ui-flash-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mx-6 mt-6 ui-flash ui-flash-error">{{ $errors->first() }}</div>
        @endif
        <main class="p-6 flex-1">@yield('admin-content')</main>
    </div>
</div>

</body>
</html>
