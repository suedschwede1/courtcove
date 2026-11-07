@extends(request('popup') ? 'layouts.popup' : 'layouts.app')
@section('title', __('booking.admin.bookings.edit_title'))

@section('content')
@php
    $date = \Carbon\Carbon::parse($reservation->date);
    $timeStartLabel = substr((string) $reservation->time_start, 0, 5);
    $timeEndLabel = substr((string) $reservation->time_end, 0, 5);
    $quantity = (int) old('quantity', $booking->quantity);
@endphp

<div class="ds-booking-page">
    <x-ui.card :title="__('booking.admin.bookings.edit_title')" padding="lg" class="ds-booking-page__card">
        <div class="ds-booking-page__stack">
            <x-ui.summary-tiles :items="[
                ['label' => __('booking.calendar.date'), 'value' => $date->isoFormat('ddd, D. MMM YYYY')],
                ['label' => __('booking.calendar.time'), 'value' => $timeStartLabel . ' – ' . $timeEndLabel . ' ' . __('booking.admin.common.clock_suffix')],
                ['label' => __('booking.calendar.court'), 'value' => $booking->square?->display_name . ' · ' . $booking->square?->name],
            ]" />

            @if($errors->has('booking'))
                <x-ui.notice tone="problem">{{ $errors->first('booking') }}</x-ui.notice>
            @endif

            @if(! empty($joinedNames))
                <x-ui.notice tone="neutral">{{ __('booking.messages.player_exchange.edit_signed_up', ['names' => implode(', ', $joinedNames)]) }}</x-ui.notice>
            @endif

            <form method="POST" action="{{ route('bookings.update', $booking) }}" class="ds-modal__form" id="booking-edit-form">
                @csrf
                @method('PUT')

                <x-ui.option-group :legend="__('booking.modal.play_type')">
                    <x-ui.option-tile name="quantity" value="2" :label="__('booking.modal.single_tile')" :checked="$quantity === 2" />
                    <x-ui.option-tile name="quantity" value="4" :label="__('booking.modal.double_tile')" :checked="$quantity === 4" />
                </x-ui.option-group>

                <x-ui.field for="mitspieler" :label="__('booking.admin.bookings.teammates')">
                    <x-ui.input name="mitspieler" maxlength="255" :value="old('mitspieler', $playerNames[2])" :placeholder="__('booking.admin.bookings.teammates_examples')" />
                </x-ui.field>

                <x-ui.button type="submit">{{ __('booking.admin.common.save') }}</x-ui.button>
            </form>

            <x-ui.button variant="secondary" :href="route('calendar.index', ['date' => $date->format('Y-m-d')])">{{ __('booking.modal.cancel') }}</x-ui.button>
        </div>
    </x-ui.card>
</div>
@endsection
