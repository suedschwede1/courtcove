@extends('layouts.app')

@section('content')
<div class="mx-auto flex w-full max-w-page flex-col gap-4 px-4 pb-10 pt-5 md:px-6">
    @include('tournament-base::partials.info-notice')
    @yield('pyramid-content')
</div>
@endsection
