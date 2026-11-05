@extends('layouts.admin')

@push('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('admin-content')
    @if(session('status'))
        <x-ui.notice tone="good">{{ session('status') }}</x-ui.notice>
    @endif

    @yield('members-content')
@endsection
