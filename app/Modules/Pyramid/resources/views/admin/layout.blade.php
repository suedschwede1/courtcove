{{-- Frame of every Pyramid admin page: the tournament area shell and the content width. --}}
@extends('tournament-base::layouts.area')

@section('admin-title', __('pyramid::admin.index.title'))

@section('admin-content')
<div class="mx-auto flex w-full min-w-0 max-w-[1100px] flex-col gap-5">
    @yield('pyramid-admin-content')
</div>
@endsection
