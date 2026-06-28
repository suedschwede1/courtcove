<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ __('booking.nav.admin') }} – @yield('admin-title', __('booking.admin.overview'))</title>
    <link rel="stylesheet" href="{{ asset('css/booking.css') }}">
</head>
<body class="admin-body-root{{ request('popup') ? ' admin-popup-mode' : '' }}">
<div class="admin-shell">

    <aside class="admin-sidebar">
        <div class="admin-sidebar__brand">{{ __('booking.nav.admin') }}</div>
        <nav class="admin-sidebar__nav">
            <a href="{{ route('admin.dashboard') }}" @class(['admin-sidebar__link', 'is-active' => request()->routeIs('admin.dashboard')])>{{ __('booking.admin.overview') }}</a>
            @if(Route::has('admin.users.index'))@can('admin.user')
            <a href="{{ route('admin.users.index') }}" @class(['admin-sidebar__link', 'is-active' => request()->routeIs('admin.users.*')])>{{ __('booking.admin.nav_users') }}</a>
            @endcan @endif
            @if(Route::has('admin.events.index'))@can('admin.event')
            <a href="{{ route('admin.events.index') }}" @class(['admin-sidebar__link', 'is-active' => request()->routeIs('admin.events.*')])>{{ __('booking.admin.nav_events') }}</a>
            @endcan @endif
            @if(Route::has('admin.bookings.index'))@can('admin.booking')
            <a href="{{ route('admin.bookings.index') }}" @class(['admin-sidebar__link', 'is-active' => request()->routeIs('admin.bookings.*')])>{{ __('booking.admin.nav_bookings') }}</a>
            @endcan @endif
            @if(Route::has('admin.squares.index'))@can('admin.config')
            <a href="{{ route('admin.squares.index') }}" @class(['admin-sidebar__link', 'is-active' => request()->routeIs('admin.squares.*')])>{{ __('booking.admin.nav_courts') }}</a>
            @endcan @endif
            @if(Route::has('admin.config.edit'))@can('admin.config')
            <a href="{{ route('admin.config.edit') }}" @class(['admin-sidebar__link', 'is-active' => request()->routeIs('admin.config.*')])>{{ __('booking.admin.config') }}</a>
            @endcan @endif
            @if(Route::has('admin.testmail.index'))@can('admin.config')
            <a href="{{ route('admin.testmail.index') }}" @class(['admin-sidebar__link', 'is-active' => request()->routeIs('admin.testmail.*')])>Testmail</a>
            @endcan @endif
        </nav>
        <div class="admin-sidebar__footer">
            <a href="{{ route('calendar.index') }}" class="admin-sidebar__link admin-sidebar__link--muted">← {{ __('booking.admin.to_calendar') }}</a>
        </div>
    </aside>

    <div class="admin-content-area">
        @if(session('success'))<div class="admin-flash admin-flash--success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="admin-flash admin-flash--error">{{ $errors->first() }}</div>@endif
        <main class="admin-main">@yield('admin-content')</main>
    </div>

</div>
</body>
</html>
