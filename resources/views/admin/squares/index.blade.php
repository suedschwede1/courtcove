@extends('layouts.admin')
@section('admin-title', __('booking.admin.squares.title'))
@section('admin-content')
<div class="ds-admin-stack">
    <x-admin.page-header :title="__('booking.admin.squares.title')" :eyebrow="__('booking.admin.nav.eyebrow')">
        <x-slot:actions>
            <x-ui.button :href="route('admin.squares.create')" icon="plus" :block="false" class="ds-admin-fab">{{ __('booking.admin.squares.new_square') }}</x-ui.button>
        </x-slot:actions>
    </x-admin.page-header>

    @if($squares->isEmpty())
        <x-ui.card :title="__('booking.admin.squares.title')">
            <p class="ds-admin-note">{{ __('booking.admin.no_results') }}</p>
        </x-ui.card>
    @else
        <x-ui.card padding="none" class="ds-admin-rows ds-admin-lists" :title="__('booking.admin.squares.title')">
            <x-ui.table :caption="__('booking.admin.squares.title')">
                <x-slot:head>
                    <tr>
                        <th scope="col">{{ __('booking.admin.squares.name') }}</th>
                        <th scope="col">{{ __('booking.admin.squares.display_name') }}</th>
                        <th scope="col">{{ __('booking.admin.squares.status') }}</th>
                        <th scope="col">{{ __('booking.admin.squares.time_column') }}</th>
                        <th scope="col">{{ __('booking.admin.squares.time_block_column') }}</th>
                        <th scope="col" class="ds-admin-actions-cell"><span class="sr-only">{{ __('booking.admin.users.actions') }}</span></th>
                    </tr>
                </x-slot:head>
                @foreach($squares as $square)
                    <tr>
                        <td class="ds-cell--title" data-label="{{ __('booking.admin.squares.name') }}">{{ $square->name }}</td>
                        <td data-label="{{ __('booking.admin.squares.display_name') }}">{{ $square->display_name }}</td>
                        <td data-label="{{ __('booking.admin.squares.status') }}">{{ __('booking.admin.squares.status_' . $square->status->value) }}</td>
                        <td data-label="{{ __('booking.admin.squares.time_column') }}">{{ substr((string) $square->time_start, 0, 5) }}–{{ substr((string) $square->time_end, 0, 5) }} {{ __('booking.admin.common.clock_suffix') }}</td>
                        <td data-label="{{ __('booking.admin.squares.time_block_column') }}">{{ (int) round($square->time_block / 60) }} {{ __('booking.admin.common.minutes_suffix') }}</td>
                        <td class="ds-admin-actions-cell" data-label="{{ __('booking.admin.users.actions') }}">
                            <div class="ds-admin-row-actions">
                                <x-ui.button :href="route('admin.squares.edit', $square)" variant="secondary" :block="false">{{ __('booking.admin.common.edit') }}</x-ui.button>
                                <form method="POST" action="{{ route('admin.squares.destroy', $square) }}" onsubmit="return confirm({{ Js::from(__('booking.admin.squares.confirm_delete')) }})">
                                    @method('DELETE') @csrf
                                    <x-ui.button type="submit" variant="danger" :block="false">{{ __('booking.admin.common.delete') }}</x-ui.button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif
</div>
@endsection
