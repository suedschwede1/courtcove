@extends('layouts.admin')
@section('admin-title', __('booking.admin.bookings.title'))
@section('admin-content')
@php
    $statusBadge = [
        'single' => ['active', __('booking.admin.bookings.status_active')],
        'subscription' => ['subscription', __('booking.admin.bookings.status_series')],
        'cancelled' => ['cancelled', __('booking.admin.bookings.status_cancelled')],
    ];
@endphp
<div class="ds-admin-stack">
    <x-admin.page-header :title="__('booking.admin.bookings.title')" :eyebrow="__('booking.admin.nav.eyebrow')">
        <p class="ds-admin-note">{{ __('booking.admin.bookings.filter_intro') }}</p>
        <x-slot:actions>
            <x-ui.button :href="route('admin.bookings.create')" icon="plus" :block="false" class="ds-admin-fab">{{ __('booking.admin.bookings.create_title') }}</x-ui.button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-ui.card padding="lg" :title="__('booking.admin.bookings.filter_heading')">
        <form method="GET" action="{{ route('admin.bookings.index') }}" class="ds-admin-filter">
            <input type="hidden" name="searched" value="1">
            <x-ui.field :label="__('booking.admin.common.court')" for="bookings-sid">
                <x-ui.select name="sid" id="bookings-sid" :options="$squares->pluck('display_name', 'sid')->all()" :selected="request('sid')" :placeholder="__('booking.admin.common.all_courts')" />
            </x-ui.field>
            <x-ui.button type="submit" :block="false">{{ __('booking.admin.common.filter') }}</x-ui.button>
        </form>
    </x-ui.card>

    @if(!$searched)
        <x-ui.card><p class="ds-admin-note">{{ __('booking.admin.bookings.search_hint') }}</p></x-ui.card>
    @elseif($bookings->isEmpty())
        <x-ui.card :title="__('booking.admin.bookings.all_bookings')">
            <p class="ds-admin-note">{{ __('booking.admin.bookings.no_results_filtered') }}</p>
        </x-ui.card>
    @else
        <x-ui.card padding="none" class="ds-admin-rows ds-admin-lists" :title="__('booking.admin.bookings.all_bookings')">
            <x-slot:actions>
                <span class="ds-admin-note">{{ __('booking.admin.bookings.entries_count', ['count' => $bookings->total()]) }}</span>
            </x-slot:actions>
            <x-ui.table :caption="__('booking.admin.bookings.all_bookings')">
                <x-slot:head>
                    <tr>
                        <th scope="col">{{ __('booking.admin.common.member') }}</th>
                        <th scope="col">{{ __('booking.admin.common.court') }}</th>
                        <th scope="col">{{ __('booking.admin.common.date') }}</th>
                        <th scope="col">{{ __('booking.admin.common.time') }}</th>
                        <th scope="col">{{ __('booking.admin.common.player') }}</th>
                        <th scope="col">{{ __('booking.admin.common.status') }}</th>
                        <th scope="col" class="ds-admin-actions-cell">{{ __('booking.admin.bookings.actions') }}</th>
                    </tr>
                </x-slot:head>
                @foreach($bookings as $b)
                    @php
                        $reservation = $b->reservations->sortBy(['date', 'time_start'])->first();
                        $badge = $statusBadge[$b->status] ?? $statusBadge['single'];
                    @endphp
                    <tr>
                        <td class="ds-cell--title" data-label="{{ __('booking.admin.common.member') }}">{{ $b->owner_label }}</td>
                        <td data-label="{{ __('booking.admin.common.court') }}">{{ $b->square?->display_name ?? __('booking.account.empty_option') }}</td>
                        <td data-label="{{ __('booking.admin.common.date') }}">{{ $reservation ? \Carbon\Carbon::parse($reservation->date)->format('d.m.Y') : __('booking.account.empty_option') }}</td>
                        <td data-label="{{ __('booking.admin.common.time') }}">{{ $reservation ? substr((string) $reservation->time_start, 0, 5) . ' - ' . substr((string) $reservation->time_end, 0, 5) : __('booking.account.empty_option') }}</td>
                        <td data-label="{{ __('booking.admin.common.player') }}">{{ $b->player_names !== [] ? implode(', ', $b->player_names) : __('booking.account.empty_option') }}</td>
                        <td data-label="{{ __('booking.admin.common.status') }}">
                            <x-ui.badge :status="$badge[0]">{{ $badge[1] }}</x-ui.badge>
                        </td>
                        <td class="ds-admin-actions-cell" data-label="{{ __('booking.admin.bookings.actions') }}">
                            <div class="ds-admin-row-actions">
                                <x-ui.button :href="route('admin.bookings.edit', $b)" variant="secondary" :block="false">{{ __('booking.admin.common.edit') }}</x-ui.button>
                                @if($b->status !== 'cancelled')
                                    <form method="POST" action="{{ route('admin.bookings.cancel', $b) }}" onsubmit="return confirm({{ Js::from(__('booking.admin.bookings.confirm_cancel')) }})">
                                        @csrf
                                        <x-ui.button type="submit" variant="secondary" :block="false">{{ __('booking.admin.common.cancel') }}</x-ui.button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.bookings.destroy', $b) }}" onsubmit="return confirm({{ Js::from(__('booking.admin.bookings.confirm_delete')) }})">
                                    @method('DELETE')
                                    @csrf
                                    <x-ui.button type="submit" variant="danger" :block="false">{{ __('booking.admin.common.delete') }}</x-ui.button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
        <x-ui.pagination :paginator="$bookings" />
    @endif
</div>
@endsection
