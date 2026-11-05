@extends('layouts.admin')

@section('admin-title', __('pyramid::admin.index.title'))

@section('admin-content')
<div class="mx-auto flex w-full max-w-[1100px] flex-col gap-5">
    @include('tournament-base::partials.info-notice')
    @yield('pyramid-admin-content')
</div>
@endsection
