@extends('members::layout')
@section('members-title', __('members::admin.dashboard.title'))
@section('members-content')
@php
    $money = fn ($sum) => \App\Modules\Members\Support\MembersFormat::money($sum);
@endphp
<x-members::page-header :title="__('members::admin.dashboard.title')">
    <x-slot:actions>
        <x-ui.button :block="false" icon="plus" :href="route('members.create')" class="mbr-phone-hidden">{{ __('members::admin.members.create') }}</x-ui.button>
    </x-slot:actions>
    <x-slot:phoneAction>
        <x-ui.button :href="route('members.create')">{{ __('members::admin.members.create') }}</x-ui.button>
    </x-slot:phoneAction>
</x-members::page-header>

<div class="mbr-kpis">
    <x-ui.kpi :label="__('members::admin.dashboard.members')" :value="$memberCount" :meta="__('members::admin.dashboard.youth', ['count' => $youthCount])" />
    <x-ui.kpi :label="__('members::admin.dashboard.active')" :value="$activeCount" :meta="__('members::admin.dashboard.dormant', ['count' => $dormantCount])" />
    <x-ui.kpi :label="$currentFiscalYear ? __('members::admin.dashboard.dues_open', ['year' => $currentFiscalYear->name]) : __('members::admin.dashboard.open_dues')" :value="$openDuesCount" :meta="__('members::admin.dashboard.dues_open_sum', ['sum' => $money($openDuesSum)]).($openHouseholdDuesCount > 0 ? ' · '.__('members::admin.dashboard.open_dues_meta_households', ['count' => $openHouseholdDuesCount]) : '')" />
    <x-ui.kpi :label="__('members::admin.dashboard.without_account')" :value="$withoutAccountCount" :meta="__('members::admin.dashboard.without_account_meta')" />
</div>

<div class="mbr-split">
    <x-ui.card :title="__('members::admin.dashboard.open_list')">
        @if($openDuesTop->isEmpty())
            <p class="text-body-s text-ink-2">{{ __('members::admin.dashboard.no_open_dues') }}</p>
        @else
            <ul class="mbr-changes">
                @foreach($openDuesTop as $row)
                    {{-- Member rows link to the member, household rows (counted once) to the household. --}}
                    <li class="mbr-due-row">
                        <a href="{{ $row['url'] }}" class="mbr-change__name">{{ $row['label'] }}</a>
                        <span class="text-ink-2">{{ $row['category'] }}@if($row['household']) · {{ __('members::admin.households.chip') }}@endif</span>
                    </li>
                @endforeach
            </ul>
        @endif
        <p class="mbr-card-foot">
            @if($openDuesTop->count() < $openDuesCount)
                <span class="text-body-s text-ink-2">{{ __('members::admin.dashboard.open_list_shown', ['shown' => $openDuesTop->count(), 'total' => $openDuesCount]) }}</span>
            @endif
            <span class="text-body-s text-ink-2">{{ __('members::admin.dashboard.paid_dues') }}: {{ $paidDuesCount }} · {{ $money($paidDuesSum) }}</span>
            <a href="{{ route('members.dues.index', ['status' => 'alle']) }}" class="mbr-text-link">{{ __('members::admin.dashboard.all_dues') }}</a>
        </p>
    </x-ui.card>

    <x-ui.card :title="__('members::admin.dashboard.recent')">
        @if($recentChanges->isEmpty())
            <p class="text-body-s text-ink-2">{{ __('members::admin.history.empty') }}</p>
        @else
            <ul class="mbr-changes">
                @foreach($recentChanges as $change)
                    @include('members::partials.change-row', ['change' => $change, 'categoryNames' => $categoryNames])
                @endforeach
            </ul>
        @endif
        <p class="mbr-card-foot">
            <a href="{{ route('members.changes.index') }}" class="mbr-text-link">{{ __('members::admin.dashboard.all_changes') }}</a>
        </p>
    </x-ui.card>
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
