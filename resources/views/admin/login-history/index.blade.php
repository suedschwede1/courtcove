@extends('layouts.admin')
@section('admin-title', __('booking.admin.login_history.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header">
        <h1>{{ __('booking.admin.login_history.title') }}</h1>
        <p>{{ __('booking.admin.login_history.intro') }}</p>
    </div>

    <div class="ui-card ui-card--filter">
        <div class="ui-card-body ui-stack">
            <p class="ui-section-label !mb-0">{{ __('booking.admin.users.filter_heading') }}</p>
            <form method="GET" action="{{ route('admin.login-history.index') }}" class="ui-row">
                <div class="ui-field min-w-[14rem] flex-1">
                    <label class="ui-label">{{ __('booking.admin.login_history.filter_user') }}</label>
                    <input type="text" name="user" value="{{ $filters['user'] ?? '' }}" class="ui-input">
                </div>
                <div class="ui-field min-w-[10rem]">
                    <label class="ui-label">{{ __('booking.admin.users.login_history_status') }}</label>
                    <select name="status" class="ui-select">
                        <option value="">{{ __('booking.admin.search_status_all') }}</option>
                        @foreach(['success', 'failed', 'throttled'] as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('booking.admin.users.login_history_status_'.$status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ui-field min-w-[8rem]">
                    <label class="ui-label">{{ __('booking.admin.users.login_history_country') }}</label>
                    <input type="text" name="country" maxlength="2" value="{{ $filters['country'] ?? '' }}" class="ui-input">
                </div>
                <div class="ui-field min-w-[10rem]">
                    <label class="ui-label">{{ __('booking.admin.login_history.filter_date_from') }}</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="ui-input">
                </div>
                <div class="ui-field min-w-[10rem]">
                    <label class="ui-label">{{ __('booking.admin.login_history.filter_date_to') }}</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="ui-input">
                </div>
                <button type="submit" class="ui-btn ui-btn-primary">{{ __('booking.admin.common.filter') }}</button>
            </form>
        </div>
    </div>

    <div class="ui-grid-4">
        <div class="ui-card ui-kpi">
            <div class="ui-card-body ui-stack">
                <p class="ui-kpi-label">{{ __('booking.admin.login_history.stat_success') }}</p>
                <p class="ui-kpi-value">{{ $statusCounts['success'] ?? 0 }}</p>
            </div>
        </div>
        <div class="ui-card">
            <div class="ui-card-body ui-stack">
                <p class="ui-kpi-label">{{ __('booking.admin.login_history.stat_failed') }}</p>
                <p class="ui-kpi-value">{{ ($statusCounts['failed'] ?? 0) + ($statusCounts['throttled'] ?? 0) }}</p>
                <p class="ui-kpi-meta">
                    {{ $statusCounts['failed'] ?? 0 }} {{ __('booking.admin.users.login_history_status_failed') }}
                    · {{ $statusCounts['throttled'] ?? 0 }} {{ __('booking.admin.users.login_history_status_throttled') }}
                </p>
            </div>
        </div>
        <div class="ui-card">
            <div class="ui-card-body ui-stack">
                <p class="ui-kpi-label">{{ __('booking.admin.login_history.stat_today') }}</p>
                <p class="ui-kpi-value">{{ $todayCount }}</p>
            </div>
        </div>
        <div class="ui-card">
            <div class="ui-card-body ui-stack">
                <p class="ui-kpi-label">{{ __('booking.admin.login_history.stat_month') }}</p>
                <p class="ui-kpi-value">{{ $monthCount }}</p>
            </div>
        </div>
    </div>

    <div class="ui-card">
        <div class="ui-card-header">
            <h2>{{ __('booking.admin.login_history.stat_countries') }}</h2>
        </div>
        <div class="ui-card-body">
            @if($countryCounts->isEmpty())
                <p class="ui-kpi-meta">{{ __('booking.admin.users.login_history_empty') }}</p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach($countryCounts as $country => $count)
                        <span class="ui-badge ui-badge-info">{{ $country }}: {{ $count }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="ui-card">
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th>{{ __('booking.admin.login_history.user') }}</th>
                        <th>{{ __('booking.admin.users.login_history_time') }}</th>
                        <th>{{ __('booking.admin.users.login_history_ip') }}</th>
                        <th>{{ __('booking.admin.users.login_history_country') }}</th>
                        <th>{{ __('booking.admin.users.login_history_status') }}</th>
                        <th>{{ __('booking.admin.users.login_history_flags') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($history as $entry)
                        <tr>
                            <td>
                                @if($entry->user)
                                    <a href="{{ route('admin.users.edit', $entry->user) }}">{{ $entry->user->alias }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $entry->created_at?->format('d.m.Y H:i') }}</td>
                            <td>{{ $entry->ip }}</td>
                            <td>{{ $entry->country_code ?? '—' }}</td>
                            <td>{{ __('booking.admin.users.login_history_status_'.$entry->status) }}</td>
                            <td>
                                @if($entry->risk_flags)
                                    {{ collect(explode(',', $entry->risk_flags))->map(fn (string $flag): string => __('booking.admin.users.login_history_flag_'.$flag))->join(', ') }}
                                @else
                                    {{ __('booking.admin.users.login_history_flags_none') }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">{{ __('booking.admin.users.login_history_empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $history->links() }}
</div>
@endsection
