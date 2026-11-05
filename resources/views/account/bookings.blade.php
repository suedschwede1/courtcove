@extends('layouts.app')
@section('title', __('booking.account.my_bookings'))

@section('content')
<div class="mx-auto flex w-full max-w-350 flex-col gap-5 px-4 py-6 md:px-6 md:py-8">
    <x-ui.page-header
        :eyebrow="__('booking.account.eyebrow')"
        :title="__('booking.account.my_bookings')"
        :intro="__('booking.account.bookings_intro')" />

    @if(session('success'))
        <x-ui.notice tone="good">{{ session('success') }}</x-ui.notice>
    @endif
    @if(session('error'))
        <x-ui.notice tone="problem">{{ session('error') }}</x-ui.notice>
    @endif
    @if($errors->has('booking'))
        <x-ui.notice tone="problem">{{ $errors->first('booking') }}</x-ui.notice>
    @endif

    <x-ui.card :title="__('booking.account.filter_heading')">
        <form method="GET" action="{{ route('account.bookings') }}" class="flex flex-col gap-4">
            <input type="hidden" name="searched" value="1">

            <div class="grid items-end gap-4 md:grid-cols-2 lg:grid-cols-5">
                <x-ui.field :label="__('booking.account.search')" for="booking-q">
                    <x-ui.input name="q" id="booking-q" :value="$filters['q'] ?? ''" :placeholder="__('booking.account.search_placeholder')" />
                </x-ui.field>

                <x-ui.field :label="__('booking.account.court')" for="booking-sid">
                    <x-ui.select name="sid" id="booking-sid"
                        :options="$squares->mapWithKeys(fn ($s) => [(string) $s->sid => $s->display_name])->all()"
                        :placeholder="__('booking.account.all_courts')"
                        :selected="$filters['sid'] ?? ''" />
                </x-ui.field>

                <x-ui.field :label="__('booking.account.status')" for="booking-status">
                    <x-ui.select name="status" id="booking-status"
                        :options="[
                            'active' => __('booking.account.status_filter_active'),
                            'single' => __('booking.account.status_active'),
                            'subscription' => __('booking.account.status_series'),
                            'cancelled' => __('booking.account.status_cancelled'),
                            'all' => __('booking.account.status_filter_all'),
                        ]"
                        :selected="$filters['status'] ?? 'active'" />
                </x-ui.field>

                <x-ui.field :label="__('booking.account.date_from')" for="booking-date-from">
                    <x-ui.input name="date_from" id="booking-date-from" type="date" :value="$filters['date_from'] ?? ''" />
                </x-ui.field>

                <x-ui.field :label="__('booking.account.date_to')" for="booking-date-to">
                    <x-ui.input name="date_to" id="booking-date-to" type="date" :value="$filters['date_to'] ?? ''" />
                </x-ui.field>
            </div>

            <div class="flex flex-wrap gap-2">
                <x-ui.button type="submit" :block="false">{{ __('booking.account.search_button') }}</x-ui.button>
                <x-ui.button variant="secondary" :href="route('account.bookings')" :block="false">{{ __('booking.account.reset_filters') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    @if(! $searched)
        <x-ui.card>
            <p class="text-body text-ink-2 text-center">{{ __('booking.account.search_hint') }}</p>
        </x-ui.card>
    @elseif($bookings->isEmpty())
        <x-ui.card>
            <div class="flex flex-col items-center gap-4">
                <p class="text-body text-ink-2 text-center">{{ __('booking.messages.no_active_bookings') }}</p>
                <x-ui.button variant="secondary" :href="route('calendar.index')" :block="false">{{ __('booking.account.to_calendar') }}</x-ui.button>
            </div>
        </x-ui.card>
    @else
        <x-ui.card padding="none">
            <x-ui.table :caption="__('booking.account.my_bookings')">
                <x-slot:head>
                    <tr>
                        <th scope="col">{{ __('booking.account.court') }}</th>
                        <th scope="col">{{ __('booking.account.date') }}</th>
                        <th scope="col">{{ __('booking.account.time') }}</th>
                        <th scope="col">{{ __('booking.account.status') }}</th>
                        <th scope="col"><span class="sr-only">{{ __('booking.account.actions') }}</span></th>
                    </tr>
                </x-slot:head>
                @foreach($bookings as $booking)
                    @php
                        $reservation = $booking->reservations->sortBy(fn ($reservation) => $reservation->date.' '.$reservation->time_start)->first();
                        $canCancel = in_array($booking->bid, $cancellableBookingIds, true);
                        $statusKey = $booking->status === 'cancelled' ? 'cancelled' : ($booking->status === 'subscription' ? 'subscription' : 'active');
                        $statusLabel = $booking->status === 'cancelled' ? __('booking.account.status_cancelled') : ($booking->status === 'subscription' ? __('booking.account.status_series') : __('booking.account.status_active'));
                    @endphp
                    <tr>
                        <td class="ds-cell--title">{{ $booking->square?->display_name }}</td>
                        <td data-label="{{ __('booking.account.date') }}">{{ $reservation?->date }}</td>
                        <td data-label="{{ __('booking.account.time') }}">@if($reservation){{ \Illuminate\Support\Str::of($reservation->time_start)->substr(0, 5) }}-{{ \Illuminate\Support\Str::of($reservation->time_end)->substr(0, 5) }} {{ __('booking.admin.common.clock_suffix') }}@endif</td>
                        <td data-label="{{ __('booking.account.status') }}"><x-ui.badge :status="$statusKey">{{ $statusLabel }}</x-ui.badge></td>
                        <td class="ds-cell--actions text-right">
                            @if($canCancel)
                                <form method="POST" action="{{ route('bookings.destroy', $booking) }}" onsubmit="return confirm({{ Js::from(__('booking.messages.confirm_cancel_booking')) }})" class="m-0 w-full md:w-auto">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="danger" :block="false" class="w-full md:w-auto">{{ __('booking.account.cancel') }}</x-ui.button>
                                </form>
                            @else
                                <x-ui.button variant="danger" disabled :block="false" class="w-full md:w-auto" :title="__('booking.account.cancel_disabled')">{{ __('booking.account.cancel') }}</x-ui.button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif
</div>
@endsection
