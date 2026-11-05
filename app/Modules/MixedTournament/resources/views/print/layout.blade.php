{{--
    Frame of the printable scorecards: a standalone A4 page without the app layout, header or admin shell
    (old setTerminal(true)). The toolbar is screen-only (no-print). Styles: app.css tokens + mixed-print.css.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title')</title>
    @vite(['resources/css/app.css', 'resources/css/mixed-print.css', 'resources/js/app.js'])
</head>
<body class="sc-page">
    <div class="sc-toolbar no-print">
        <x-ui.button :block="false" x-data x-on:click="window.print()">{{ __('mixed::print.print') }}</x-ui.button>
        <x-ui.button variant="secondary" :block="false" icon="chevron-left" :href="$backUrl">{{ __('mixed::print.back') }}</x-ui.button>
    </div>

    @yield('content')
</body>
</html>
