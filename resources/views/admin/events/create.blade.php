@extends(request('popup') ? 'layouts.popup' : 'layouts.admin')
@section('title', __('booking.admin.events.create_title'))
@section('admin-title', __('booking.admin.events.create_title'))
@unless(request('popup'))
    @section('admin-back', route('admin.events.index'))
@endunless
@section('admin-content')
@php
    $bookingUrl = route('admin.bookings.create', array_filter([
        'sid'        => request('sid'),
        'date'       => request('date_start'),
        'time_start' => request('time_start'),
        'time_end'   => request('time_end'),
        'popup'      => request('popup') ?: null,
    ]));
@endphp
<div class="ds-admin-stack ds-admin-form-page">
    <x-admin.page-header :title="__('booking.admin.events.create_title')" :eyebrow="__('booking.admin.nav.eyebrow')" :back-url="route('admin.events.index')" :back-label="__('booking.admin.events.title')">
        <p class="ds-admin-note">{{ __('booking.admin.events.create_intro') }}</p>
    </x-admin.page-header>

    <x-ui.tabs :label="__('booking.admin.bookings.create_title')" :items="[
        ['label' => __('booking.admin.bookings.type_booking'), 'url' => $bookingUrl],
        ['label' => __('booking.admin.bookings.type_event'), 'url' => request()->fullUrl(), 'active' => true],
    ]" />

    <form method="POST" action="{{ route('admin.events.store') }}" class="ds-admin-stack">
        @if(request('popup'))
            <input type="hidden" name="popup" value="1">
            <input type="hidden" name="redirect_to" value="{{ route('calendar.index') }}">
        @endif

        <x-ui.card :title="__('booking.admin.events.section_event')" padding="lg" class="ds-admin-form-card">
            @include('admin.events._form', ['squares' => $squares])
        </x-ui.card>

        <div class="ds-admin-actions ds-admin-actions--sticky">
            <div class="ds-admin-actions__group ds-admin-actions__group--main">
                <x-ui.button :href="route('admin.events.index')" variant="secondary" :block="false">{{ __('booking.admin.common.abort') }}</x-ui.button>
                <x-ui.button type="submit" :block="false" class="ds-admin-actions__save">{{ __('booking.admin.common.create') }}</x-ui.button>
            </div>
        </div>
    </form>
</div>
@endsection
