@extends('layouts.admin')
@section('admin-title', __('booking.admin.bookings.title'))
@section('admin-back', route('admin.bookings.index'))
@section('admin-content')
@php
    $statusBadge = [
        'single' => ['active', __('booking.admin.bookings.status_active')],
        'subscription' => ['subscription', __('booking.admin.bookings.status_series')],
        'cancelled' => ['cancelled', __('booking.admin.bookings.status_cancelled')],
    ][$booking->status] ?? ['active', __('booking.admin.bookings.status_active')];
@endphp
<div class="ds-admin-stack ds-admin-form-page">
    <x-admin.page-header :title="__('booking.admin.bookings.title').' #'.$booking->bid" :eyebrow="__('booking.admin.nav.eyebrow')" :back-url="route('admin.bookings.index')" :back-label="__('booking.admin.bookings.title')">
        <x-ui.badge :status="$statusBadge[0]">{{ $statusBadge[1] }}</x-ui.badge>
        <x-slot:actions>
            <x-ui.button :href="route('admin.bookings.edit', $booking)" variant="secondary" :block="false">{{ __('booking.admin.bookings.edit_title') }}</x-ui.button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-ui.card padding="none" :title="__('booking.admin.bookings.title')">
        <x-ui.table variant="kv">
            <tr><th scope="row">{{ __('booking.admin.bookings.booked_for') }}</th><td>{{ $booking->owner_label }}</td></tr>
            <tr><th scope="row">{{ __('booking.admin.common.court') }}</th><td>{{ $booking->square?->display_name ?? __('booking.account.empty_option') }}</td></tr>
            <tr><th scope="row">{{ __('booking.admin.common.status') }}</th><td>{{ $statusBadge[1] }}</td></tr>
            <tr><th scope="row">{{ __('booking.admin.common.player') }}</th><td>{{ $booking->player_names !== [] ? implode(', ', $booking->player_names) : __('booking.account.empty_option') }}</td></tr>
        </x-ui.table>
    </x-ui.card>

    <x-ui.card padding="none" class="ds-admin-rows" :title="__('booking.admin.bookings.reservations')">
        <x-ui.table :caption="__('booking.admin.bookings.reservations')">
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('booking.admin.common.date') }}</th>
                    <th scope="col">{{ __('booking.admin.common.from') }}</th>
                    <th scope="col">{{ __('booking.admin.common.to') }}</th>
                </tr>
            </x-slot:head>
            @foreach($booking->reservations as $reservation)
                <tr>
                    <td class="ds-cell--title" data-label="{{ __('booking.admin.common.date') }}">{{ $reservation->date }}</td>
                    <td data-label="{{ __('booking.admin.common.from') }}">{{ $reservation->time_start }}</td>
                    <td data-label="{{ __('booking.admin.common.to') }}">{{ $reservation->time_end }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    <div class="ds-admin-actions">
        <x-ui.button :href="route('admin.bookings.index')" variant="text" :block="false">{{ __('booking.admin.common.back') }}</x-ui.button>
        <div class="ds-admin-actions__group">
            @if($booking->status !== 'cancelled')
                <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" onsubmit="return confirm({{ Js::from(__('booking.admin.bookings.confirm_cancel')) }})">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" :block="false">{{ __('booking.admin.common.cancel') }}</x-ui.button>
                </form>
            @endif
            <form method="POST" action="{{ route('admin.bookings.destroy', $booking) }}" onsubmit="return confirm({{ Js::from(__('booking.admin.bookings.confirm_delete')) }})">
                @method('DELETE') @csrf
                <x-ui.button type="submit" variant="danger" :block="false">{{ __('booking.admin.bookings.delete_permanent') }}</x-ui.button>
            </form>
        </div>
    </div>
</div>
@endsection
