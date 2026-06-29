@extends('layouts.admin')
@section('admin-title', __('booking.admin.events.edit_title'))
@section('admin-content')
<div class="flex flex-col gap-6">
    @unless(request('popup'))
    <h1 class="text-2xl font-bold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.events.edit_title') }}</h1>
    @endunless

    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.events.edit_title') }}</h2>
        </div>
        <div class="px-6 py-5">
            <form method="POST" action="{{ route('admin.events.update', $event) }}">
                @csrf
                @method('PUT')
                @if(request('popup'))
                <input type="hidden" name="popup" value="1">
                <input type="hidden" name="redirect_to" value="{{ route('calendar.index', ['date' => $date_start ?: now()->format('Y-m-d')]) }}">
                @endif
                @include('admin.events._form')
                <div class="mt-6">
                    <button type="submit" class="bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium px-5 py-2 rounded transition-colors">{{ __('booking.admin.common.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
