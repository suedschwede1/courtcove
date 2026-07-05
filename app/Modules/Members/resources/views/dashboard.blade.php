@extends('members::layout')
@section('admin-title', __('members::admin.dashboard.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header"><h1>{{ __('members::admin.dashboard.title') }}</h1></div>

    <div class="ui-grid-3">
        <div class="ui-card ui-kpi">
            <div class="ui-card-body">
                <p class="ui-kpi-label">{{ __('members::admin.dashboard.active_members') }}</p>
                <p class="ui-kpi-value">{{ $activeCount }}</p>
            </div>
        </div>
        <div class="ui-card ui-kpi">
            <div class="ui-card-body">
                <p class="ui-kpi-label">{{ __('members::admin.dashboard.open_dues') }}</p>
                <p class="ui-kpi-value">{{ $openDuesCount }}</p>
                <p class="ui-kpi-meta">{{ number_format((float) $openDuesSum, 2) }} €</p>
            </div>
        </div>
        <div class="ui-card ui-kpi">
            <div class="ui-card-body">
                <p class="ui-kpi-label">{{ __('members::admin.dashboard.by_category') }}</p>
                <p class="ui-kpi-value">{{ count($categoryCounts) }}</p>
            </div>
        </div>
    </div>

    <div class="ui-dashboard-lower">
        <div class="ui-card">
            <div class="ui-card-header"><h2>{{ __('members::admin.dashboard.age_structure') }}</h2></div>
            <div class="ui-card-body">
                @foreach($ageStructure['buckets'] as $bucket => $count)
                    <div class="ui-age-row">
                        <span>{{ __('members::admin.dashboard.age_'.$bucket) }}</span>
                        <span>{{ $count }}</span>
                    </div>
                @endforeach
                <div class="ui-age-row">
                    <span>{{ __('members::admin.dashboard.age_unknown') }}</span>
                    <span>{{ $ageStructure['unknown'] }}</span>
                </div>
            </div>
        </div>

        <div class="ui-card">
            <div class="ui-card-header"><h2>{{ __('members::admin.dashboard.by_category') }}</h2></div>
            <div class="ui-card-body">
                @php $maxCategoryCount = $categoryCounts->max() ?: 0; @endphp
                @forelse($categoryCounts as $categoryName => $count)
                    <div class="ui-category-bar-row">
                        <div class="ui-category-bar-label">
                            <span>{{ $categoryName }}</span>
                            <span>{{ $count }}</span>
                        </div>
                        <div class="ui-category-bar-track">
                            <div class="ui-category-bar-fill" style="width: {{ $maxCategoryCount > 0 ? round($count / $maxCategoryCount * 100) : 0 }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="ui-kpi-meta">—</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
