@extends(request('popup') ? 'layouts.popup' : 'layouts.admin')
@section('title', __('booking.admin.events.edit_title'))
@section('admin-title', __('booking.admin.events.edit_title'))
@unless(request('popup'))
    @section('admin-back', route('admin.events.index'))
@endunless

@section('content')
<div class="ds-admin-stack ds-admin-form-page">
    {{-- In the popup the frame header already names the page. --}}
    @unless(request('popup'))
        <x-admin.page-header :title="__('booking.admin.events.edit_title')" :eyebrow="__('booking.admin.nav.eyebrow')" :back-url="route('admin.events.index')" :back-label="__('booking.admin.events.title')" />
    @endunless

    <form method="POST" action="{{ route('admin.events.update', $event) }}" class="ds-admin-stack">
        @csrf
        @method('PUT')
        @if(request('popup'))
            <input type="hidden" name="popup" value="1">
            <input type="hidden" name="redirect_to" value="{{ route('calendar.index', ['date' => $date_start ?: now()->format('Y-m-d')]) }}">
        @endif

        <x-ui.card :title="__('booking.admin.events.section_event')" padding="lg" class="ds-admin-form-card">
            @include('admin.events._form', ['popup_mode' => request('popup')])
        </x-ui.card>

        <div class="ds-admin-actions ds-admin-actions--sticky">
            <div class="ds-admin-actions__group ds-admin-actions__group--main">
                <x-ui.button :href="route('admin.events.index')" variant="secondary" :block="false">{{ __('booking.admin.common.abort') }}</x-ui.button>
                <x-ui.button type="submit" :block="false" class="ds-admin-actions__save">{{ __('booking.admin.common.save') }}</x-ui.button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('admin-content')
@include('admin.events._booking-conflicts')
    @yield('content')
@endsection
