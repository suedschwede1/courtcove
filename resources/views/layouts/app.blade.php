<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ExampleClub Buchung')</title>
    <style>
        .cc-free { background:#EEE; }
        .cc-own { background:#8BB243; color:#fff; }
        .cc-single-future, .cc-multiple-future { background:#2596be; color:#fff; }
        .cc-spielersuche { background:#a024bf; color:#fff; }
        table { border-collapse:collapse; width:100%; }
        td, th { border:1px solid #ccc; padding:4px 8px; }
        .toolbar { display:flex; gap:12px; align-items:center; margin-bottom:1rem; }
    </style>
</head>
<body>
<nav style="padding:8px; background:#f5f5f5; margin-bottom:1rem;">
    <strong>ExampleClub Buchungssystem</strong>
    @auth
        &nbsp;|&nbsp; {{ auth()->user()->name }}
        &nbsp;|&nbsp;
        <form method="POST" action="/logout" style="display:inline">
            @csrf<button type="submit">Abmelden</button>
        </form>
    @endauth
    @guest <a href="/login">Anmelden</a> @endguest
</nav>
@yield('content')
</body>
</html>
