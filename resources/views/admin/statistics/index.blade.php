@extends('layouts.admin')
@section('admin-title', __('booking.admin.statistics.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header">
        <h1>{{ __('booking.admin.statistics.title') }}</h1>
    </div>

    <div class="ui-grid-4">
        <div class="ui-card ui-kpi">
            <div class="ui-card-body ui-stack">
                <p class="ui-kpi-label">{{ __('booking.admin.statistics.summary_total') }}</p>
                <p class="ui-kpi-value">{{ $summary['total'] }}</p>
                <p class="ui-kpi-meta">{{ $summary['single'] }} {{ __('booking.admin.bookings.single') }} · {{ $summary['double'] }} {{ __('booking.admin.bookings.double') }}</p>
            </div>
        </div>
        <div class="ui-card">
            <div class="ui-card-body ui-stack">
                <p class="ui-kpi-label">{{ __('booking.admin.statistics.summary_last_month') }}</p>
                <p class="ui-kpi-value">{{ $summary['lastMonth'] }}</p>
            </div>
        </div>
        <div class="ui-card">
            <div class="ui-card-body ui-stack">
                <p class="ui-kpi-label">{{ __('booking.admin.statistics.summary_cancellation_rate') }}</p>
                <p class="ui-kpi-value">{{ number_format($summary['cancellationRate'], 1) }}%</p>
            </div>
        </div>
    </div>

    <div class="ui-card">
        <div class="ui-table-wrap">
            @if($stats->isEmpty())
                <div class="ui-card-body"><p class="ui-kpi-meta">{{ __('booking.admin.no_results') }}</p></div>
            @else
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>{{ __('booking.admin.statistics.member') }}</th>
                            <th>{{ __('booking.admin.statistics.total') }}</th>
                            <th>{{ __('booking.admin.statistics.single') }}</th>
                            <th>{{ __('booking.admin.statistics.double') }}</th>
                            <th>{{ __('booking.admin.statistics.last_month') }}</th>
                            <th>{{ __('booking.admin.statistics.top_court') }}</th>
                            <th>{{ __('booking.admin.statistics.cancellation_rate') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stats as $row)
                            <tr>
                                <td class="font-medium">{{ $row['alias'] }}</td>
                                <td>{{ $row['total'] }}</td>
                                <td>{{ $row['single'] }}</td>
                                <td>{{ $row['double'] }}</td>
                                <td>{{ $row['lastMonth'] }}</td>
                                <td>{{ $row['topCourt'] ?? '—' }}</td>
                                <td>{{ number_format($row['cancellationRate'], 1) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
