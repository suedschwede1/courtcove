@extends('layouts.admin')
@section('admin-title', __('booking.admin.bookings.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1>{{ __('booking.admin.bookings.title') }}</h1>
            <p>{{ __('booking.admin.bookings.filter_intro') }}</p>
        </div>
        <a href="{{ route('admin.bookings.create') }}" class="ui-btn ui-btn-primary">{{ __('booking.admin.bookings.create_title') }}</a>
    </div>

    <div class="ui-card">
        <div class="ui-card-body ui-stack">
            <p class="ui-section-label !mb-0">{{ __('booking.admin.bookings.filter_heading') }}</p>
            <form method="GET" action="{{ route('admin.bookings.index') }}" class="ui-row">
                <input type="hidden" name="searched" value="1">
                <div class="ui-field min-w-[14rem]">
                    <label class="ui-label">{{ __('booking.admin.common.court') }}</label>
                    <select name="sid" class="ui-select">
                        <option value="">{{ __('booking.admin.common.all_courts') }}</option>
                        @foreach($squares as $square)
                            <option value="{{ $square->sid }}" @selected(request('sid') == $square->sid)>{{ $square->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="ui-btn ui-btn-primary">{{ __('booking.admin.common.filter') }}</button>
            </form>
        </div>
    </div>

    @if(!$searched)
        <div class="ui-card">
            <div class="ui-card-body">
                <p class="ui-kpi-meta">{{ __('booking.admin.bookings.search_hint') }}</p>
            </div>
        </div>
    @else
    <div class="ui-card">
        <div class="ui-card-header">
            <div>
                <h2>{{ __('booking.admin.bookings.all_bookings') }}</h2>
                <p class="ui-kpi-meta mt-1">{{ __('booking.admin.bookings.entries_count', ['count' => $bookings->total()]) }}</p>
            </div>
        </div>
        @if($bookings->isEmpty())
            <div class="ui-card-body">
                <p class="ui-kpi-meta">{{ __('booking.admin.bookings.no_results_filtered') }}</p>
            </div>
        @else
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>{{ __('booking.admin.common.member') }}</th>
                            <th>{{ __('booking.admin.common.court') }}</th>
                            <th>{{ __('booking.admin.common.date') }}</th>
                            <th>{{ __('booking.admin.common.time') }}</th>
                            <th>{{ __('booking.admin.common.player') }}</th>
                            <th>{{ __('booking.admin.common.status') }}</th>
                            <th class="text-right">{{ __('booking.admin.bookings.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bookings as $b)
                            @php($reservation = $b->reservations->sortBy(['date', 'time_start'])->first())
                            <tr>
                                <td class="font-medium">{{ $b->owner_label }}</td>
                                <td class="text-[#6a6e73]">{{ $b->square?->display_name ?? __('booking.account.empty_option') }}</td>
                                <td class="text-[#6a6e73]">{{ $reservation ? \Carbon\Carbon::parse($reservation->date)->format('d.m.Y') : __('booking.account.empty_option') }}</td>
                                <td class="text-[#6a6e73]">{{ $reservation ? substr((string) $reservation->time_start, 0, 5) . ' - ' . substr((string) $reservation->time_end, 0, 5) : __('booking.account.empty_option') }}</td>
                                <td class="text-[#6a6e73]">{{ $b->player_names !== [] ? implode(', ', $b->player_names) : __('booking.account.empty_option') }}</td>
                                <td>
                                    <span class="ui-badge {{ $b->status === 'cancelled' ? 'ui-badge-danger' : ($b->status === 'subscription' ? 'ui-badge-info' : 'ui-badge-success') }}">{{ $b->status === 'cancelled' ? __('booking.admin.bookings.status_cancelled') : ($b->status === 'subscription' ? __('booking.admin.bookings.status_series') : __('booking.admin.bookings.status_active')) }}</span>
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                        <a href="{{ route('admin.bookings.edit', $b) }}" class="ui-btn ui-btn-ghost">{{ __('booking.admin.common.edit') }}</a>
                                        @if($b->status !== 'cancelled')
                                            <form method="POST" action="{{ route('admin.bookings.cancel', $b) }}" class="inline" onsubmit="return confirm({{ Js::from(__('booking.admin.bookings.confirm_cancel')) }})">
                                                @csrf
                                                <button type="submit" class="ui-btn ui-btn-outline">{{ __('booking.admin.common.cancel') }}</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.bookings.destroy', $b) }}" class="inline" onsubmit="return confirm({{ Js::from(__('booking.admin.bookings.confirm_delete')) }})">
                                            @method('DELETE')
                                            @csrf
                                            <button type="submit" class="ui-btn ui-btn-danger">{{ __('booking.admin.common.delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="ui-card-body">
                {{ $bookings->links() }}
            </div>
        @endif
    </div>
    @endif
</div>
@endsection
