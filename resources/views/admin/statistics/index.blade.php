@extends('layouts.admin')
@section('admin-title', __('booking.admin.statistics.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header">
        <h1>{{ __('booking.admin.statistics.title') }}</h1>
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
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stats as $row)
                            <tr>
                                <td class="font-medium">{{ $row['alias'] }}</td>
                                <td>{{ $row['total'] }}</td>
                                <td>{{ $row['single'] }}</td>
                                <td>{{ $row['double'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
