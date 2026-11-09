@extends('layouts.app')
@section('title', __('booking.calendar.book_court', ['court' => $square->display_name]))

@section('content')

@php
$sprueche = __('booking.quotes');
$spruch = $sprueche[array_rand($sprueche)];

$timeStartSeconds = $slotStart ?? $timeStart * 3600;
$timeEndSeconds = $slotEnd ?? $timeEnd * 3600;
$timeStartLabel = gmdate('H:i', $timeStartSeconds);
$timeEndLabel = gmdate('H:i', $timeEndSeconds);
@endphp

<div class="ds-booking-page">
    <x-ui.card :title="__('booking.calendar.book_court', ['court' => $square->display_name])" padding="lg" class="ds-booking-page__card">
        <div class="ds-booking-page__stack">
            <x-ui.summary-tiles :items="[
                ['label' => __('booking.calendar.date'), 'value' => $date->isoFormat('ddd, D. MMM YYYY')],
                ['label' => __('booking.calendar.time'), 'value' => $timeStartLabel . ' – ' . $timeEndLabel . ' ' . __('booking.admin.common.clock_suffix')],
                ['label' => __('booking.calendar.court'), 'value' => $square->display_name . ' · ' . $square->name],
            ]" />

            <p class="ds-modal__quote">{{ $spruch }}</p>

            @if($errors->has('booking'))
                <x-ui.notice tone="problem">{{ $errors->first('booking') }}</x-ui.notice>
            @endif

            <form method="POST" action="{{ route('bookings.store') }}" class="ds-modal__form">
                @csrf
                <input type="hidden" name="sid"        value="{{ $square->sid }}">
                <input type="hidden" name="date"       value="{{ $date->format('Y-m-d') }}">
                <input type="hidden" name="time_start" value="{{ $timeStartSeconds }}">
                <input type="hidden" name="time_end"   value="{{ $timeEndSeconds }}">
                <input type="hidden" name="quantity"   value="2">

                <x-ui.field for="mitspieler" :label="__('booking.admin.bookings.teammates')">
                    <x-ui.input name="mitspieler" maxlength="255" :placeholder="__('booking.admin.bookings.teammates_examples')" />
                </x-ui.field>

                <x-ui.button type="submit">{{ __('booking.modal.complete_booking') }}</x-ui.button>
            </form>

            <x-ui.button variant="secondary" :href="route('calendar.index', ['date' => $date->format('Y-m-d')])">{{ __('booking.modal.cancel') }}</x-ui.button>
        </div>
    </x-ui.card>
</div>
@endsection
