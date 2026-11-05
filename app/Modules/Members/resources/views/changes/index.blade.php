@extends('members::layout')
@section('members-title', __('members::admin.changes.title'))
@section('members-content')
<x-members::page-header :title="__('members::admin.changes.title')" />

<x-ui.card class="mbr-filter-card" as="div">
    <form method="GET" action="{{ route('members.changes.index') }}" class="mbr-filters">
        <x-ui.field class="mbr-filters__search" :label="__('members::admin.changes.search')" for="mc-search">
            <x-ui.input name="search" :value="$filters['search'] ?? ''" />
        </x-ui.field>
        <x-ui.field class="mbr-filters__field" :label="__('members::admin.changes.from')" for="mc-from">
            <x-ui.input type="date" name="from" :value="$filters['from'] ?? ''" />
        </x-ui.field>
        <x-ui.field class="mbr-filters__field" :label="__('members::admin.changes.to')" for="mc-to">
            <x-ui.input type="date" name="to" :value="$filters['to'] ?? ''" />
        </x-ui.field>
        <x-ui.button type="submit" variant="secondary" :block="false" class="mbr-filters__submit">{{ __('members::admin.common.filter') }}</x-ui.button>
    </form>
</x-ui.card>

<x-ui.card :title="__('members::admin.changes.list_title')">
    @if($changes->isEmpty())
        <p class="text-body-s text-ink-2">{{ __('members::admin.changes.empty') }}</p>
    @else
        <ul class="mbr-changes">
            @foreach($changes as $change)
                @include('members::partials.change-row', ['change' => $change, 'categoryNames' => $categoryNames])
            @endforeach
        </ul>
    @endif
    @if($changes->hasPages())
        <div class="mbr-table-foot">
            <x-ui.pagination :paginator="$changes" />
        </div>
    @endif
</x-ui.card>
@endsection
