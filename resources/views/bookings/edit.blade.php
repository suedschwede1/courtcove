@extends(request('popup') ? 'layouts.popup' : 'layouts.app')
@section('title', __('booking.admin.bookings.edit_title'))

@section('content')
@php
    $date = \Carbon\Carbon::parse($reservation->date);
    $timeStartLabel = substr((string) $reservation->time_start, 0, 5);
    $timeEndLabel = substr((string) $reservation->time_end, 0, 5);
@endphp

<div class="booking-confirm-page">
    <div class="panel booking-confirm-card">
        <h2 class="booking-confirm-title">{{ __('booking.admin.bookings.edit_title') }}</h2>

        <table class="booking-confirm-summary">
            <tr>
                <td>{{ __('booking.calendar.court') }}</td>
                <td>{{ $booking->square?->display_name ?? $booking->square?->name }}</td>
            </tr>
            <tr>
                <td>{{ __('booking.calendar.date') }}</td>
                <td>{{ $date->isoFormat('dddd, D. MMMM YYYY') }}</td>
            </tr>
            <tr>
                <td>{{ __('booking.calendar.time') }}</td>
                <td>{{ $timeStartLabel }} - {{ $timeEndLabel }} Uhr</td>
            </tr>
        </table>

        @if($errors->has('booking'))
            <p class="ui-error">{{ $errors->first('booking') }}</p>
        @endif

        <form method="POST" action="{{ route('bookings.update', $booking) }}" class="booking-confirm-form" id="booking-edit-form">
            @csrf
            @method('PUT')

            <label class="booking-confirm-field">
                {{ __('booking.admin.bookings.player_count') }}
                <select name="quantity" id="booking-edit-quantity">
                    <option value="2" @selected((int) old('quantity', $booking->quantity) === 2)>{{ __('booking.modal.single') }}</option>
                    <option value="4" @selected((int) old('quantity', $booking->quantity) === 4)>{{ __('booking.modal.double') }}</option>
                </select>
            </label>

            <label class="booking-confirm-field" data-player-field="2">
                {{ __('booking.modal.player_name_2') }}
                <input type="text" name="player_name_2" value="{{ old('player_name_2', $playerNames[2]) }}" maxlength="120" list="player-suggestions" required>
            </label>

            <label class="booking-confirm-field" data-player-field="3">
                {{ __('booking.modal.player_name_3') }}
                <input type="text" name="player_name_3" value="{{ old('player_name_3', $playerNames[3]) }}" maxlength="120" list="player-suggestions">
            </label>

            <label class="booking-confirm-field" data-player-field="4">
                {{ __('booking.modal.player_name_4') }}
                <input type="text" name="player_name_4" value="{{ old('player_name_4', $playerNames[4]) }}" maxlength="120" list="player-suggestions">
            </label>

            <datalist id="player-suggestions"></datalist>

            <button type="submit" class="default-button booking-confirm-submit">
                {{ __('booking.admin.common.save') }}
            </button>
        </form>

        <div class="booking-confirm-cancel">
            <a href="{{ route('calendar.index', ['date' => $date->format('Y-m-d')]) }}">{{ __('booking.modal.cancel') }}</a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var quantity = document.getElementById('booking-edit-quantity');
    var fields = document.querySelectorAll('[data-player-field]');

    function syncFields() {
        var isDouble = quantity && quantity.value === '4';
        fields.forEach(function (field) {
            var index = field.getAttribute('data-player-field');
            var input = field.querySelector('input');
            var visible = index === '2' || isDouble;
            field.hidden = !visible;
            if (input) {
                input.required = visible;
                if (!visible) {
                    input.value = '';
                }
            }
        });
    }

    if (quantity) {
        quantity.addEventListener('change', syncFields);
        syncFields();
    }
});
</script>
@endsection