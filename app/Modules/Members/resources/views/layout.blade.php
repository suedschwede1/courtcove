{{--
    Layout of the Members area: the admin shell (layouts.admin) with its own sidebar navigation (MembersNavigation:
    group Mitglieder with Übersicht, Mitglieder, Beiträge, Rundmail, Protokoll; group Einstellungen with the settings
    pages) and the phone bar with the slide-in menu. Each page brings x-members::page-header (eyebrow, h1, actions) and fills
    members-content.
--}}
@extends('layouts.admin')

@section('layout-title')
    @yield('members-title') - {{ __('members::admin.area.eyebrow') }}
@endsection

@section('admin-title')
    @yield('members-title')
@endsection

@section('admin-eyebrow', __('members::admin.area.eyebrow'))

@section('admin-sidebar')
    <x-layout.admin-sidebar :groups="\App\Modules\Members\Support\MembersNavigation::groups(auth()->user(), request())" :eyebrow="__('booking.nav.members')" :label="__('members::admin.area.nav')" />
@endsection

@push('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('admin-content')
    @if(session('status'))
        <x-ui.notice tone="good">{{ session('status') }}</x-ui.notice>
    @endif

    @yield('members-content')

    @stack('members-phone-action')
@endsection
