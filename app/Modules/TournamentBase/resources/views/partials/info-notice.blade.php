@if (session('info'))
<x-ui.notice tone="good">{{ session('info') }}</x-ui.notice>
@endif
