@extends('members::layout')
@section('members-title', __('members::admin.households.tab_suggestions'))
@section('members-content')
@php
    $reasonLabels = [
        'address' => __('members::admin.households.suggestions.reason_address'),
        'email' => __('members::admin.households.suggestions.reason_email'),
        'phone' => __('members::admin.households.suggestions.reason_phone'),
        'lastname' => __('members::admin.households.suggestions.reason_lastname'),
    ];
@endphp
<x-members::page-header :title="__('members::admin.households.tab_suggestions')">
    <x-slot:actions>
        <x-ui.button variant="secondary" :block="false" :href="route('members.households.index')">{{ __('members::admin.households.back') }}</x-ui.button>
    </x-slot:actions>
</x-members::page-header>

{{-- Decision 8: suggestions only, nothing is created automatically; the processing purpose is named. --}}
<x-ui.notice tone="neutral">
    <p class="m-0">{{ __('members::admin.households.suggestions.only_suggestions') }}</p>
    <p class="m-0 text-body-s">{{ __('members::admin.dsgvo.purpose_households') }}</p>
</x-ui.notice>

@if($errors->hasAny(['signature', 'member_ids', 'fee_category_id', 'name']))
    <x-ui.notice tone="attention">{{ $errors->first('signature') ?: ($errors->first('member_ids') ?: ($errors->first('fee_category_id') ?: $errors->first('name'))) }}</x-ui.notice>
@endif

@forelse($suggestions as $i => $suggestion)
    <x-ui.card :title="__('members::admin.households.suggestions.card_title', ['number' => $i + 1])" data-testid="household-suggestion">
        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap gap-2">
                @foreach($suggestion->reasons as $reason)
                    <x-ui.badge tone="neutral">{{ $reasonLabels[$reason] ?? $reason }}</x-ui.badge>
                @endforeach
            </div>
            <form method="POST" action="{{ route('members.households.suggestions.confirm') }}" class="flex flex-col gap-3">
                @csrf
                <input type="hidden" name="signature" value="{{ $suggestion->signature }}">
                {{-- Option tiles: 44px tap targets on phones; untick a member to leave them out. --}}
                <x-ui.option-group :legend="__('members::admin.households.suggestions.members')">
                    @foreach($suggestion->memberIds as $memberId)
                        @php $member = $members[$memberId] ?? null; @endphp
                        @if($member)
                            <x-ui.option-tile type="checkbox" name="member_ids[]" :value="$member->id" :checked="true"
                                :label="$member->fullName().' ('.__('members::admin.members.number_prefix', ['number' => $member->member_number]).')'" />
                        @endif
                    @endforeach
                </x-ui.option-group>
                <div class="mbr-filters">
                    <x-ui.field class="mbr-filters__field" :label="__('members::admin.households.name')" for="name" :id="'sg-name-'.$i">
                        <x-ui.input maxlength="128" />
                    </x-ui.field>
                    <x-ui.field class="mbr-filters__field" :label="__('members::admin.households.tariff')" for="fee_category_id" :id="'sg-tariff-'.$i">
                        <x-ui.select :options="$tariffs->pluck('name', 'id')->all()" :placeholder="__('members::admin.households.tariff_none')" />
                    </x-ui.field>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-ui.button type="submit" :block="false">{{ __('members::admin.households.suggestions.confirm') }}</x-ui.button>
                    <x-ui.button type="submit" variant="secondary" :block="false" :form="'sg-dismiss-'.$i">{{ __('members::admin.households.suggestions.dismiss') }}</x-ui.button>
                </div>
            </form>
            <form id="sg-dismiss-{{ $i }}" method="POST" action="{{ route('members.households.suggestions.dismiss') }}" hidden>
                @csrf
                <input type="hidden" name="signature" value="{{ $suggestion->signature }}">
            </form>
        </div>
    </x-ui.card>
@empty
    <x-ui.card>
        <p class="m-0 text-ink-2">{{ __('members::admin.households.suggestions.empty') }}</p>
    </x-ui.card>
@endforelse
@endsection
