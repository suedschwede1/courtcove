{{-- Frame of every Mixed player page: the tournament area shell, the content width and the module warning notice. --}}
@extends('tournament-base::layouts.area')

@section('admin-content')
<div class="mx-auto flex w-full min-w-0 max-w-page flex-col gap-4">
    @include('mixed::partials.flash')
    @yield('mixed-content')
</div>
@endsection
