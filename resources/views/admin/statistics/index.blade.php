@extends('layouts.admin')
@section('admin-title', __('booking.admin.statistics.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header">
        <h1>{{ __('booking.admin.statistics.title') }}</h1>
    </div>

    <div class="ui-card ui-card--filter">
        <div class="ui-card-body ui-stack">
            <form method="GET" action="{{ route('admin.statistics.index') }}" class="ui-row">
                <input type="hidden" name="searched" value="1">
                <button type="submit" class="ui-btn ui-btn-primary">{{ __('booking.admin.common.filter') }}</button>
            </form>
        </div>
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

    @if($searched)
        <div class="ui-card">
            <div class="ui-table-wrap">
                @if($stats->isEmpty())
                    <div class="ui-card-body"><p class="ui-kpi-meta">{{ __('booking.admin.no_results') }}</p></div>
                @else
                    <table class="ui-table" id="statistics-table">
                        <thead>
                            <tr>
                                <th data-sort="text">{{ __('booking.admin.statistics.member') }}</th>
                                <th data-sort="number">{{ __('booking.admin.statistics.total') }}</th>
                                <th data-sort="number">{{ __('booking.admin.statistics.single') }}</th>
                                <th data-sort="number">{{ __('booking.admin.statistics.double') }}</th>
                                <th data-sort="number">{{ __('booking.admin.statistics.last_month') }}</th>
                                <th data-sort="text">{{ __('booking.admin.statistics.top_court') }}</th>
                                <th data-sort="number">{{ __('booking.admin.statistics.cancellation_rate') }}</th>
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
    @else
        <div class="ui-card"><div class="ui-card-body"><p class="ui-kpi-meta">{{ __('booking.admin.search_hint') }}</p></div></div>
    @endif
</div>

<script>
(function () {
    var table = document.getElementById('statistics-table');
    if (!table) { return; }

    var headers = table.querySelectorAll('thead th');
    var currentSort = { index: -1, dir: 1 };

    headers.forEach(function (th, index) {
        th.style.cursor = 'pointer';
        th.addEventListener('click', function () {
            var type = th.getAttribute('data-sort');
            var dir = currentSort.index === index ? -currentSort.dir : 1;
            currentSort = { index: index, dir: dir };

            var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
            rows.sort(function (a, b) {
                var aText = a.children[index].textContent.trim();
                var bText = b.children[index].textContent.trim();
                if (type === 'number') {
                    var aVal = parseFloat(aText.replace('%', '').replace('—', '-1')) || 0;
                    var bVal = parseFloat(bText.replace('%', '').replace('—', '-1')) || 0;
                    return (aVal - bVal) * dir;
                }
                return aText.localeCompare(bText) * dir;
            });

            var tbody = table.querySelector('tbody');
            rows.forEach(function (row) { tbody.appendChild(row); });
        });
    });
})();
</script>
@endsection
