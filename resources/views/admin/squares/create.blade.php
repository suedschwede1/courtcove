@extends('layouts.admin')
@section('admin-title', __('booking.admin.squares.create_title'))
@section('admin-content')
<div class="flex flex-col gap-6">
    <h1 class="text-2xl font-bold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.squares.create_title') }}</h1>
    <form method="POST" action="{{ route('admin.squares.store') }}" class="flex flex-col gap-6">
        @include('admin.squares._form', ['form' => $form, 'square' => $square])
        <div>
            <button type="submit" class="bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium px-5 py-2 rounded transition-colors">{{ __('booking.admin.common.create') }}</button>
        </div>
    </form>
</div>
@endsection
