@extends('layouts.admin')
@section('admin-title', __('booking.admin.statistics.title'))
@section('admin-content')
@php
    $columns = [
        ['label' => __('booking.admin.statistics.member'), 'sort' => 'text'],
        ['label' => __('booking.admin.statistics.total'), 'sort' => 'number'],
        ['label' => __('booking.admin.statistics.single'), 'sort' => 'number'],
        ['label' => __('booking.admin.statistics.double'), 'sort' => 'number'],
        ['label' => __('booking.admin.statistics.last_month'), 'sort' => 'number'],
        ['label' => __('booking.admin.statistics.this_year'), 'sort' => 'number'],
        ['label' => __('booking.admin.statistics.top_court'), 'sort' => 'text'],
        ['label' => __('booking.admin.statistics.cancellation_rate'), 'sort' => 'number'],
    ];
@endphp
<div class="ds-admin-stack">
    <x-admin.page-header :title="__('booking.admin.statistics.title')" />

    <x-ui.card padding="lg">
        <form method="GET" action="{{ route('admin.statistics.index') }}" class="ds-admin-filter">
            <input type="hidden" name="searched" value="1">
            <x-ui.field :label="__('booking.admin.statistics.search')" for="stats-search">
                <x-ui.input name="search" id="stats-search" :value="$search ?? ''" />
            </x-ui.field>
            <x-ui.button type="submit" :block="false">{{ __('booking.admin.common.filter') }}</x-ui.button>
        </form>
    </x-ui.card>

    <div class="ds-admin-kpis">
        <x-ui.card as="div" class="ds-admin-kpi">
            <x-ui.kpi :label="__('booking.admin.statistics.summary_total')" :value="$summary['total']" :meta="$summary['single'] . ' ' . __('booking.admin.bookings.single') . ' · ' . $summary['double'] . ' ' . __('booking.admin.bookings.double')" />
        </x-ui.card>
        <x-ui.card as="div" class="ds-admin-kpi">
            <x-ui.kpi :label="__('booking.admin.statistics.summary_this_year')" :value="$summary['thisYear']" />
        </x-ui.card>
        <x-ui.card as="div" class="ds-admin-kpi">
            <x-ui.kpi :label="__('booking.admin.statistics.summary_last_month')" :value="$summary['lastMonth']" />
        </x-ui.card>
        <x-ui.card as="div" class="ds-admin-kpi">
            <x-ui.kpi :label="__('booking.admin.statistics.summary_cancellation_rate')" :value="number_format($summary['cancellationRate'], 1) . '%'" />
        </x-ui.card>
        <x-ui.card as="div" class="ds-admin-kpi">
            <x-ui.kpi :label="__('booking.admin.statistics.summary_members_this_year')" :value="$summary['membersThisYear']" />
        </x-ui.card>
        <x-ui.card as="div" class="ds-admin-kpi">
            <x-ui.kpi :label="__('booking.admin.statistics.summary_members_this_month')" :value="$summary['membersThisMonth']" />
        </x-ui.card>
    </div>

    @if($searched)
        @if($stats->isEmpty())
            <x-ui.card><p class="ds-admin-note">{{ __('booking.admin.no_results') }}</p></x-ui.card>
        @else
            <x-ui.card padding="none" class="ds-admin-rows">
                <x-ui.table id="statistics-table" :caption="__('booking.admin.statistics.title')">
                    <x-slot:head>
                        <tr>
                            @foreach($columns as $column)
                                <th scope="col" data-sort="{{ $column['sort'] }}" class="ds-admin-sortable">{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </x-slot:head>
                    @foreach($stats as $row)
                        <tr>
                            <td class="ds-cell--title" data-label="{{ $columns[0]['label'] }}">{{ $row['alias'] }}</td>
                            <td data-label="{{ $columns[1]['label'] }}">{{ $row['total'] }}</td>
                            <td data-label="{{ $columns[2]['label'] }}">{{ $row['single'] }}</td>
                            <td data-label="{{ $columns[3]['label'] }}">{{ $row['double'] }}</td>
                            <td data-label="{{ $columns[4]['label'] }}">{{ $row['lastMonth'] }}</td>
                            <td data-label="{{ $columns[5]['label'] }}">{{ $row['thisYear'] }}</td>
                            <td data-label="{{ $columns[6]['label'] }}">{{ $row['topCourt'] ?? '—' }}</td>
                            <td data-label="{{ $columns[7]['label'] }}">{{ number_format($row['cancellationRate'], 1) }}%</td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </x-ui.card>
        @endif
    @else
        <x-ui.card><p class="ds-admin-note">{{ __('booking.admin.search_hint') }}</p></x-ui.card>
    @endif
</div>

<script>
(function () {
    var table = document.getElementById('statistics-table');
    if (!table) { return; }

    var headers = table.querySelectorAll('thead th');
    var currentSort = { index: -1, dir: 1 };

    headers.forEach(function (th, index) {
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
