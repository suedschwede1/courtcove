<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('booking.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
    @stack('head')
</head>
<body @hasSection('body-class') class="@yield('body-class')" @endif>
@yield('body')
@stack('scripts')
</body>
<!-- {{ config('booking.watermark') }} -->
</html>
