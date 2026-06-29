<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ __('booking.nav.admin') }} – @yield('admin-title', __('booking.admin.overview'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Red+Hat+Display:wght@600;700&family=Red+Hat+Text:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/booking.css') }}">
</head>
<body class="{{ request('popup') ? 'admin-popup-mode' : '' }}" style="font-family: var(--font-body)">

<div class="flex min-h-screen">
    @unless(request('popup'))
        <x-layout.admin-sidebar />
    @endunless

    <div class="flex-1 bg-[#fafafa] flex flex-col">
        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-500 text-green-800 px-6 py-3 text-sm">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="bg-red-50 border-l-4 border-red-500 text-red-800 px-6 py-3 text-sm">
                {{ $errors->first() }}
            </div>
        @endif
        <main class="p-6 flex-1">@yield('admin-content')</main>
    </div>
</div>

</body>
</html>
