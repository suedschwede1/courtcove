@extends('members::layout')
@section('admin-title', __('members::admin.dashboard.title'))
@section('members-content')
<x-members::page-header :title="__('members::admin.dashboard.title')" />

<div class="mbr-kpis">
    <x-ui.kpi :label="__('members::admin.dashboard.active_members')" :value="$activeCount" />
    <x-ui.kpi :label="__('members::admin.dashboard.open_dues')" :value="$openDuesCount" :meta="number_format((float) $openDuesSum, 2).' €'" />
    <x-ui.kpi :label="__('members::admin.dashboard.paid_dues')" :value="$paidDuesCount" :meta="number_format((float) $paidDuesSum, 2).' €'" />
</div>

<div class="mbr-split">
    <x-ui.card :title="__('members::admin.dashboard.age_structure')">
        @php
            $maxAgeCount = max([...array_values($ageStructure['buckets']), $ageStructure['unknown']]) ?: 0;
            $ageRows = [];
            foreach ($ageStructure['buckets'] as $bucket => $count) {
                $ageRows[__('members::admin.dashboard.age_'.$bucket)] = $count;
            }
            $ageRows[__('members::admin.dashboard.age_unknown')] = $ageStructure['unknown'];
        @endphp
        @foreach($ageRows as $label => $count)
            <div class="mbr-bar-row">
                <div class="mbr-bar-row__label">
                    <span>{{ $label }}</span>
                    <span>{{ $count }}</span>
                </div>
                <progress class="mbr-bar" max="{{ $maxAgeCount }}" value="{{ $count }}" aria-label="{{ $label }}"></progress>
            </div>
        @endforeach
    </x-ui.card>

    <x-ui.card :title="__('members::admin.dashboard.members_by_category')">
        @php $maxCategoryCount = $categoryBreakdown ? max($categoryBreakdown) : 0; @endphp
        @foreach($categoryBreakdown as $name => $count)
            <div class="mbr-bar-row">
                <div class="mbr-bar-row__label">
                    <span>{{ $name }}</span>
                    <span>{{ $count }}</span>
                </div>
                <progress class="mbr-bar" max="{{ $maxCategoryCount }}" value="{{ $count }}" aria-label="{{ $name }}"></progress>
            </div>
        @endforeach
    </x-ui.card>
</div>
@endsection
