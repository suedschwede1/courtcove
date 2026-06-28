<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>@yield('title', config('app.name'))</title>
    <link rel="stylesheet" href="{{ asset('css/booking.css') }}">
</head>
<body class="popup-body-root">
    @if(session('success'))<div class="success-message">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="error-message">{{ $errors->first() }}</div>@endif
    @yield('content')
</body>
</html>