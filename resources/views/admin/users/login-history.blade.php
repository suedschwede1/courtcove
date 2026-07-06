@extends('layouts.admin')
@section('admin-title', __('booking.admin.users.login_history_title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header">
        <h1>{{ __('booking.admin.users.login_history_title') }}</h1>
        <p>{{ $user->alias }} — {{ __('booking.admin.users.login_history_risk_score') }}: {{ $riskScore }}</p>
    </div>

    <div class="ui-card">
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
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
                            <td colspan="5">{{ __('booking.admin.users.login_history_empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $history->links() }}

    <a href="{{ route('admin.users.edit', $user) }}" class="ui-btn ui-btn-ghost">{{ __('booking.admin.common.abort') }}</a>
</div>
@endsection
