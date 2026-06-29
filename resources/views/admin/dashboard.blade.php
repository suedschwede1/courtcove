@extends('layouts.admin')
@section('admin-title', __('booking.admin.dashboard.title'))
@section('admin-content')
    <div class="flex flex-col gap-6">
        <h1 class="text-2xl font-bold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.overview') }}</h1>
        <hr class="border-[#f0ede6] my-2">
        <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-[#f0ede6]">
                <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.dashboard.title') }}</h2>
            </div>
            <div class="px-6 py-5">
                <nav>
                    <ul class="flex flex-col gap-3">
                        @if(Route::has('admin.users.index'))
                            @can('admin.user')
                                <li><a href="{{ route('admin.users.index') }}" class="text-sm text-[#bf4316] hover:underline">{{ __('booking.admin.dashboard.user_mgmt_link') }}</a></li>
                            @endcan
                        @endif
                        @if(Route::has('admin.events.index'))
                            @can('admin.event')
                                <li><a href="{{ route('admin.events.index') }}" class="text-sm text-[#bf4316] hover:underline">{{ __('booking.admin.dashboard.events_link') }}</a></li>
                            @endcan
                        @endif
                        @if(Route::has('admin.bookings.index'))
                            @can('admin.booking')
                                <li><a href="{{ route('admin.bookings.index') }}" class="text-sm text-[#bf4316] hover:underline">{{ __('booking.admin.dashboard.bookings_link') }}</a></li>
                            @endcan
                        @endif
                        @if(Route::has('admin.config.edit'))
                            @can('admin.config')
                                <li><a href="{{ route('admin.config.edit') }}" class="text-sm text-[#bf4316] hover:underline">{{ __('booking.admin.dashboard.config_link') }}</a></li>
                            @endcan
                        @endif
                    </ul>
                </nav>
            </div>
        </div>
    </div>
@endsection
