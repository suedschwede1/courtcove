{{--
    Frame of every Mixed admin page: the tournament area shell (sidebar module Mixed-Turnier with the current event's
    pages), the content width and the module warning notice. Success/error/info flashes come from the shell.
--}}
@extends('tournament-base::layouts.area')

@section('admin-title', __('mixed::admin.shell_title'))

@section('admin-content')
<div class="mx-auto flex w-full min-w-0 max-w-page flex-col gap-5">
    @include('mixed::partials.flash')
    @yield('mixed-admin-content')
    {{-- Player views rendered in the admin frame (adminView: Durchmischung, Wertung) fill mixed-content. --}}
    @yield('mixed-content')
</div>
@endsection
