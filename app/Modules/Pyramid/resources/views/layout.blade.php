{{-- Frame of every Pyramid page for players and guests: the tournament area shell and the content width. --}}
@extends('tournament-base::layouts.area')

@section('admin-content')
<div class="mx-auto flex w-full min-w-0 max-w-page flex-col gap-4">
    @yield('pyramid-content')
</div>
@endsection
