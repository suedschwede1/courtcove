@extends('layouts.admin')
@section('admin-title', __('booking.admin.events.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1>{{ __('booking.admin.events.title') }}</h1>
            <p>{{ __('booking.admin.events.intro_index') }}</p>
        </div>
        <a href="{{ route('admin.events.create') }}" class="ui-btn ui-btn-primary">{{ __('booking.admin.events.new_event') }}</a>
    </div>

    <div class="ui-card ui-card--filter">
        <div class="ui-card-body ui-stack">
            <p class="ui-section-label !mb-0">{{ __('booking.admin.users.filter_heading') }}</p>
            <form method="GET" action="{{ route('admin.events.index') }}" class="ui-row">
                <div class="ui-field min-w-[16rem] flex-1">
                    <label class="ui-label">{{ __('booking.admin.events.name') }}</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('booking.admin.events.name') }} ..." class="ui-input">
                </div>
                <div class="ui-field min-w-[13rem]">
                    <label class="ui-label">{{ __('booking.admin.events.court') }}</label>
                    <select name="sid" class="ui-select">
                        <option value="">{{ __('booking.admin.common.all_courts') }}</option>
                        @foreach($squares as $sq)
                            <option value="{{ $sq->sid }}" @selected((string)($filters['sid'] ?? '') === (string)$sq->sid)>{{ $sq->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ui-field min-w-[12rem]">
                    <label class="ui-label">{{ __('booking.admin.events.date_start') }}</label>
                    <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="ui-input">
                </div>
                <button type="submit" class="ui-btn ui-btn-primary">{{ __('booking.admin.common.filter') }}</button>
            </form>
        </div>
    </div>

    @if($searched)
        <div class="ui-card">
            <div class="ui-card-header">
                <div>
                    <h2>{{ __('booking.admin.events.title') }}</h2>
                    <p class="ui-kpi-meta mt-1">{{ __('booking.admin.events.results_count', ['count' => $events->count()]) }}</p>
                </div>
            </div>
            @if($events->isEmpty())
                <div class="ui-card-body"><p class="ui-kpi-meta">{{ __('booking.admin.no_results') }}</p></div>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>{{ __('booking.admin.events.name') }}</th>
                                <th>{{ __('booking.admin.events.court') }}</th>
                                <th>{{ __('booking.admin.events.from') }}</th>
                                <th>{{ __('booking.admin.events.to') }}</th>
                                <th>{{ __('booking.admin.events.status') }}</th>
                                <th class="text-right">{{ __('booking.admin.events.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($events as $event)
                                <tr>
                                    <td class="font-medium">{{ $event->meta->firstWhere('key', 'name')?->value ?? __('booking.account.empty_option') }}</td>
                                    <td class="text-[#6a6e73]">{{ $event->square?->display_name ?? __('booking.admin.events.all_courts') }}</td>
                                    <td class="text-[#6a6e73]">{{ $event->datetime_start?->format('d.m.Y H:i') }}</td>
                                    <td class="text-[#6a6e73]">{{ $event->datetime_end?->format('d.m.Y H:i') }}</td>
                                    <td><span class="ui-badge ui-badge-info">{{ $event->status === 'enabled' ? __('booking.admin.events.status_enabled') : $event->status }}</span></td>
                                    <td>
                                        <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                            <a href="{{ route('admin.events.edit', $event) }}" class="ui-btn ui-btn-ghost">{{ __('booking.admin.common.edit') }}</a>
                                            <form method="POST" action="{{ route('admin.events.destroy', $event) }}" class="inline" onsubmit="return confirm({{ Js::from(__('booking.admin.events.confirm_delete')) }})">
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
            @endif
        </div>
    @else
        <div class="ui-card"><div class="ui-card-body"><p class="ui-kpi-meta">{{ __('booking.admin.search_hint') }}</p></div></div>
    @endif
</div>
@endsection
