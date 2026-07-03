@extends('members::layout')
@section('admin-title', __('members::admin.dashboard.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header"><h1>{{ __('members::admin.dashboard.title') }}</h1></div>

    <div class="ui-grid-3">
        <div class="ui-card">
            <div class="ui-card-body">
                <p class="ui-kpi-meta">{{ __('members::admin.dashboard.active_members') }}</p>
                <p class="text-3xl font-bold">{{ $activeCount }}</p>
            </div>
        </div>
        <div class="ui-card">
            <div class="ui-card-body">
                <p class="ui-kpi-meta">{{ __('members::admin.dashboard.open_dues') }}</p>
                <p class="text-3xl font-bold">{{ $openDuesCount }}</p>
                <p class="ui-kpi-meta">{{ number_format((float) $openDuesSum, 2) }} €</p>
            </div>
        </div>
        <div class="ui-card">
            <div class="ui-card-body">
                <p class="ui-kpi-meta">{{ __('members::admin.dashboard.by_category') }}</p>
                @forelse($categoryCounts as $categoryName => $count)
                    <p>{{ $categoryName }}: {{ $count }}</p>
                @empty
                    <p class="ui-kpi-meta">—</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
