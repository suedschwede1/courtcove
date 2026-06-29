@extends('layouts.admin')
@section('admin-title', __('booking.admin.users.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1>{{ __('booking.admin.users.title') }}</h1>
            <p>Suche nach Alias oder E-Mail und filtere den Status gezielt.</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="ui-btn ui-btn-primary">{{ __('booking.admin.users.new_user') }}</a>
    </div>

    <div class="ui-card ui-card--filter">
        <div class="ui-card-body ui-stack">
            <p class="ui-section-label !mb-0">Filter</p>
            <form method="GET" action="{{ route('admin.users.index') }}" class="ui-row">
                <div class="ui-field min-w-[16rem] flex-1">
                    <label class="ui-label">{{ __('booking.admin.search_placeholder') }}</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('booking.admin.search_placeholder') }}" class="ui-input">
                </div>
                <div class="ui-field min-w-[12rem]">
                    <label class="ui-label">{{ __('booking.admin.users.status') }}</label>
                    <select name="status" class="ui-select">
                        <option value="">{{ __('booking.admin.search_status_all') }}</option>
                        @foreach(['admin' => 'status_admin', 'assist' => 'status_assist', 'enabled' => 'status_enabled', 'disabled' => 'status_disabled', 'blocked' => 'status_blocked', 'deleted' => 'status_deleted', 'placeholder' => 'status_placeholder'] as $val => $key)
                            <option value="{{ $val }}" @selected(($filters['status'] ?? '') === $val)>{{ __('booking.admin.users.'.$key) }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="ui-btn ui-btn-primary">{{ __('booking.admin.common.filter') }}</button>
            </form>
        </div>
    </div>

    @if($searched)
        <div class="ui-card">
            <div class="ui-card-header">
                <div>
                    <h2>Mitglieder</h2>
                    <p class="ui-kpi-meta mt-1">{{ $users->count() }} Treffer</p>
                </div>
            </div>
            @if($users->isEmpty())
                <div class="ui-card-body"><p class="ui-kpi-meta">{{ __('booking.admin.no_results') }}</p></div>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>{{ __('booking.admin.users.name') }}</th>
                                <th>{{ __('booking.admin.users.email') }}</th>
                                <th>{{ __('booking.admin.users.status') }}</th>
                                <th>Buchungen</th>
                                <th class="text-right">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $u)
                                <tr>
                                    <td class="font-medium">{{ $u->alias }}</td>
                                    <td class="text-[#6a6e73]">{{ $u->email ?: '—' }}</td>
                                    <td>
                                        <span class="ui-badge {{ in_array($u->status, ['enabled', 'assist', 'admin'], true) ? 'ui-badge-success' : 'ui-badge-info' }}">{{ $u->status }}</span>
                                    </td>
                                    <td class="text-[#6a6e73]">{{ $u->bookings()->count() }}</td>
                                    <td>
                                        <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                            <a href="{{ route('admin.users.edit', $u) }}" class="ui-btn ui-btn-ghost">{{ __('booking.admin.common.edit') }}</a>
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
