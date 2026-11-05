{{--
    The module flash list (ModuleFlash): every message of the last action as its own notice, in order.
    Single success/error flashes (session 'success'/'error') stay with the layout's feedback dialog.
--}}
@php
    $flashTone = ['success' => 'good', 'error' => 'problem', 'info' => 'neutral'];
@endphp
@foreach (\App\Modules\TournamentBase\Support\ModuleFlash::all() as $flash)
<x-ui.notice :tone="$flashTone[$flash['type']] ?? 'neutral'">{{ $flash['text'] }}</x-ui.notice>
@endforeach
