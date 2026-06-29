@extends(request('popup') ? 'layouts.popup' : 'layouts.admin')
@section('title', __('booking.admin.events.edit_title'))
@section('admin-title', __('booking.admin.events.edit_title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header">
        <h1>{{ __('booking.admin.events.edit_title') }}</h1>
        <p>Zeitraum, Sichtbarkeit und Platz einer bestehenden Veranstaltung zentral pflegen.</p>
    </div>

    <form method="POST" action="{{ route('admin.events.update', $event) }}" class="ui-form-shell">
        @csrf
        @method('PUT')
        @if(request('popup'))
            <input type="hidden" name="popup" value="1">
            <input type="hidden" name="redirect_to" value="{{ route('calendar.index', ['date' => $date_start ?: now()->format('Y-m-d')]) }}">
        @endif

        <div class="ui-card">
            <div class="ui-card-header"><h2>{{ __('booking.admin.events.edit_title') }}</h2></div>
            <div class="ui-card-body">
                @include('admin.events._form')
            </div>
        </div>

        <div class="ui-card">
            <div class="ui-card-body ui-form-actions">
                <div class="ui-form-actions-group">
                    <button type="submit" class="ui-btn ui-btn-primary">{{ __('booking.admin.common.save') }}</button>
                    <a href="{{ route('admin.events.index') }}" class="ui-btn ui-btn-ghost">{{ __('booking.admin.common.abort') }}</a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
