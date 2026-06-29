@extends('layouts.app')
@section('title', __('booking.calendar.book_court', ['court' => $square->name]))

@section('content')

@php
$sprueche = __('booking.quotes');
$spruch = $sprueche[array_rand($sprueche)];

$timeStartSeconds = $timeStart * 3600;
$timeEndSeconds   = $timeEnd   * 3600;

$timeStartLabel = str_pad((string) $timeStart, 2, '0', STR_PAD_LEFT) . ':00';
$timeEndLabel   = str_pad((string) $timeEnd,   2, '0', STR_PAD_LEFT) . ':00';
@endphp

<div class="booking-confirm-page">
    <div class="panel booking-confirm-card">

        <h2 class="booking-confirm-title">
            {{ __('booking.calendar.book_court', ['court' => $square->name]) }}
        </h2>

        <table class="booking-confirm-summary">
            <tr>
                <td>{{ __('booking.calendar.court') }}</td>
                <td>
                    {{ $square->name }}
                    @if($square->alias)
                        <span class="booking-confirm-summary__alias">– {{ $square->alias }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td>{{ __('booking.calendar.date') }}</td>
                <td>
                    {{ $date->isoFormat('dddd, D. MMMM YYYY') }}
                </td>
            </tr>
            <tr>
                <td>{{ __('booking.calendar.time') }}</td>
                <td>
                    {{ $timeStartLabel }} – {{ $timeEndLabel }} Uhr
                </td>
            </tr>
        </table>

        <p class="booking-confirm-quote">
            {{ $spruch }}
        </p>

        @if($errors->has('booking'))
            <p class="ui-error">
                {{ $errors->first('booking') }}
            </p>
        @endif

        <form method="POST" action="{{ route('bookings.store') }}" class="booking-confirm-form">
            @csrf
            <input type="hidden" name="sid"        value="{{ $square->sid }}">
            <input type="hidden" name="date"       value="{{ $date->format('Y-m-d') }}">
            <input type="hidden" name="time_start" value="{{ $timeStartSeconds }}">
            <input type="hidden" name="time_end"   value="{{ $timeEndSeconds }}">
            <input type="hidden" name="quantity"   value="2">

            <label class="booking-confirm-field">
                {{ __('booking.modal.player_name_2') }}
                <input type="text" name="player_name_2" value="{{ old('player_name_2') }}" maxlength="120" required>
            </label>

            <button type="submit" class="default-button booking-confirm-submit">
                {{ __('booking.modal.complete_booking') }}
            </button>
        </form>

        <div class="booking-confirm-cancel">
            <a href="{{ route('calendar.index', ['date' => $date->format('Y-m-d')]) }}">
                {{ __('booking.modal.cancel') }}
            </a>
        </div>

    </div>
</div>
@endsection
