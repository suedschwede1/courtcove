{{--
    Module notice above the page content: a warning, e.g. a rule hint after a redirect. Success, error and info
    flashes are shown by the area shell (layouts.admin).
--}}
@if (session('warning'))
<x-ui.notice tone="attention">{{ session('warning') }}</x-ui.notice>
@endif
