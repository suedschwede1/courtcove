{{--
    Frame of every Mixed admin page: the admin shell (sidebar group "Turniere", entry "Mixed-Turniere"),
    the content width and the module warning notice. Success/error/info flashes come from layouts.admin.
--}}
@extends('layouts.admin')

@section('admin-title', __('mixed::admin.shell_title'))

@section('admin-content')
<div class="mx-auto flex w-full max-w-page flex-col gap-5">
    @if (session('warning'))
        <x-ui.notice tone="attention">{{ session('warning') }}</x-ui.notice>
    @endif
    @yield('mixed-admin-content')
</div>
@endsection
