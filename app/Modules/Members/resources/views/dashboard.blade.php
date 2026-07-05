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
                <p class="ui-kpi-label">{{ __('members::admin.dashboard.paid_dues') }}</p>
                <p class="ui-kpi-value">{{ $paidDuesCount }}</p>
                <p class="ui-kpi-meta">{{ number_format((float) $paidDuesSum, 2) }} €</p>
            </div>
        </div>
    </div>

    <div class="ui-card">
        <div class="ui-card-header"><h2>{{ __('members::admin.dashboard.age_structure') }}</h2></div>
        <div class="ui-card-body">
            @php $maxAgeCount = max([...array_values($ageStructure['buckets']), $ageStructure['unknown']]) ?: 0; @endphp
            @foreach($ageStructure['buckets'] as $bucket => $count)
                <div class="ui-category-bar-row">
                    <div class="ui-category-bar-label">
                        <span>{{ __('members::admin.dashboard.age_'.$bucket) }}</span>
                        <span>{{ $count }}</span>
                    </div>
                    <div class="ui-category-bar-track">
                        <div class="ui-category-bar-fill" style="width: {{ $maxAgeCount > 0 ? round($count / $maxAgeCount * 100) : 0 }}%"></div>
                    </div>
                </div>
            @endforeach
            <div class="ui-category-bar-row">
                <div class="ui-category-bar-label">
                    <span>{{ __('members::admin.dashboard.age_unknown') }}</span>
                    <span>{{ $ageStructure['unknown'] }}</span>
                </div>
                <div class="ui-category-bar-track">
                    <div class="ui-category-bar-fill" style="width: {{ $maxAgeCount > 0 ? round($ageStructure['unknown'] / $maxAgeCount * 100) : 0 }}%"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
