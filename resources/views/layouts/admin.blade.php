<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ __('booking.nav.admin') }} – @yield('admin-title', __('booking.admin.overview'))</title>
    <link rel="stylesheet" href="{{ asset('css/booking.css') }}">
</head>
<body>
<div class="page-shell">
    <header class="top-header">
        <div class="brand-title">{{ __('booking.nav.admin') }}</div>
        <nav class="admin-nav">
            <a href="{{ route('admin.dashboard') }}" class="default-button">{{ __('booking.admin.overview') }}</a>
            @if(Route::has('admin.users.index'))@can('admin.user')<a href="{{ route('admin.users.index') }}" class="default-button">{{ __('booking.admin.users') }}</a>@endcan @endif
            @if(Route::has('admin.events.index'))@can('admin.event')<a href="{{ route('admin.events.index') }}" class="default-button">{{ __('booking.admin.events') }}</a>@endcan @endif
            @if(Route::has('admin.bookings.index'))@can('admin.booking')<a href="{{ route('admin.bookings.index') }}" class="default-button">{{ __('booking.admin.bookings') }}</a>@endcan @endif
            @if(Route::has('admin.squares.index'))@can('admin.config')<a href="{{ route('admin.squares.index') }}" class="default-button">{{ __('booking.admin.courts') }}</a>@endcan @endif
            @if(Route::has('admin.config.edit'))@can('admin.config')<a href="{{ route('admin.config.edit') }}" class="default-button">{{ __('booking.admin.config') }}</a>@endcan @endif
            <a href="{{ route('calendar.index') }}" class="default-button">{{ __('booking.admin.to_calendar') }}</a>
        </nav>
    </header>
    @if(session('success'))<div class="success-message">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="error-message">{{ $errors->first() }}</div>@endif
    <main>@yield('admin-content')</main>
</div>
</body>
</html>
