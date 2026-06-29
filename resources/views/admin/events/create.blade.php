@extends(request('popup') ? 'layouts.popup' : 'layouts.admin')
@section('title', __('booking.admin.events.create_title'))
@section('admin-title', __('booking.admin.events.create_title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header">
        <h1>{{ __('booking.admin.events.create_title') }}</h1>
        <p>Neue Veranstaltung oder Sperre mit Zeitraum, Platz und Kapazität anlegen.</p>
    </div>

    @php
        $bookingUrl = route('admin.bookings.create', array_filter([
            'sid'        => request('sid'),
            'date'       => request('date_start'),
            'time_start' => request('time_start'),
            'time_end'   => request('time_end'),
            'popup'      => request('popup') ?: null,
        ]));
    @endphp

    <div class="ui-card">
        <div class="ui-card-body ui-card-body-compact">
            <div class="ui-pane-switch">
                <a href="{{ $bookingUrl }}" class="ui-btn ui-btn-ghost">{{ __('booking.admin.bookings.type_booking') }}</a>
                <span class="ui-btn ui-btn-primary">{{ __('booking.admin.bookings.type_event') }}</span>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.events.store') }}" class="ui-form-shell">
        @if(request('popup'))
            <input type="hidden" name="popup" value="1">
            <input type="hidden" name="redirect_to" value="{{ route('calendar.index') }}">
        @endif

        <div class="ui-card">
            <div class="ui-card-header"><h2>{{ __('booking.admin.events.create_title') }}</h2></div>
            <div class="ui-card-body">
                @include('admin.events._form', ['squares' => $squares])
            </div>
        </div>

        <div class="ui-card">
            <div class="ui-card-body ui-form-actions">
                <div class="ui-form-actions-group">
                    <button type="submit" class="ui-btn ui-btn-primary">{{ __('booking.admin.common.create') }}</button>
                    <a href="{{ route('admin.events.index') }}" class="ui-btn ui-btn-ghost">{{ __('booking.admin.common.abort') }}</a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
