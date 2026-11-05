{{--
    Layout of the standalone Members area: the normal app header (layouts.app, "Mitglieder" active), no admin
    sidebar. Each page brings x-members::page-header (eyebrow, h1, tab row) and fills members-content.
--}}
@extends('layouts.app')

@section('title')
    @yield('members-title') - {{ __('members::admin.area.eyebrow') }}
@endsection

@push('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
<div class="mx-auto flex w-full max-w-350 flex-col gap-6 px-4 py-6 md:px-6 md:py-8">
    @if(session('status'))
        <x-ui.notice tone="good">{{ session('status') }}</x-ui.notice>
    @endif
    @if(session('info'))
        <x-ui.notice tone="neutral">{{ session('info') }}</x-ui.notice>
    @endif
    @if($errors->any())
        <x-ui.notice tone="problem">{{ $errors->first() }}</x-ui.notice>
    @endif

    @yield('members-content')

    @stack('members-phone-action')
</div>
@endsection
