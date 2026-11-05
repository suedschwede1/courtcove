{{-- Frame of every Mixed player page: public layout, page gutter, module flash notices. --}}
@extends('layouts.app')

@section('content')
<div class="mx-auto flex w-full max-w-page flex-col gap-4 px-4 pb-10 pt-5 md:px-6">
    @include('mixed::partials.flash')
    @yield('mixed-content')
</div>
@endsection
