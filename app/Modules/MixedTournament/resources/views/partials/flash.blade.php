{{--
    Module notices above the page content. Success and error flashes are shown by the layout (feedback
    dialog); this adds the info notice (tournament base) and a warning, e.g. a rule hint after a redirect.
--}}
@include('tournament-base::partials.info-notice')
@if (session('warning'))
<x-ui.notice tone="attention">{{ session('warning') }}</x-ui.notice>
@endif
