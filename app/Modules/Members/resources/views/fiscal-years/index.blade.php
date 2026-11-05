@extends('members::layout')
@section('admin-title', __('members::admin.fiscal_years.title'))
@section('members-content')
<x-members::page-header :title="__('members::admin.fiscal_years.title')">
    <x-slot:actions>
        <x-ui.button :block="false" icon="plus" :href="route('members.fiscal-years.create')">{{ __('members::admin.fiscal_years.new') }}</x-ui.button>
    </x-slot:actions>
</x-members::page-header>

<x-ui.card class="mbr-table-card" padding="none" as="div">
    <x-ui.table :caption="__('members::admin.fiscal_years.title')">
        <x-slot:head>
            <tr>
                <th scope="col">{{ __('members::admin.fiscal_years.name') }}</th>
                <th scope="col">{{ __('members::admin.fiscal_years.starts_on') }}</th>
                <th scope="col">{{ __('members::admin.fiscal_years.ends_on') }}</th>
                <th scope="col">{{ __('members::admin.fiscal_years.due_date') }}</th>
                <th scope="col" class="mbr-cell-actions">{{ __('members::admin.common.actions') }}</th>
            </tr>
        </x-slot:head>
        @foreach($fiscalYears as $fiscalYear)
            <tr>
                <td class="ds-cell--title" data-label="{{ __('members::admin.fiscal_years.name') }}">{{ $fiscalYear->name }}</td>
                <td data-label="{{ __('members::admin.fiscal_years.starts_on') }}">{{ $fiscalYear->starts_on->format('d.m.Y') }}</td>
                <td data-label="{{ __('members::admin.fiscal_years.ends_on') }}">{{ $fiscalYear->ends_on->format('d.m.Y') }}</td>
                <td data-label="{{ __('members::admin.fiscal_years.due_date') }}">{{ optional($fiscalYear->faellig_am)->format('d.m.Y') ?? '–' }}</td>
                <td class="mbr-cell-actions" data-label="{{ __('members::admin.common.actions') }}">
                    <x-ui.button variant="secondary" :block="false" :href="route('members.fiscal-years.edit', $fiscalYear)">{{ __('members::admin.common.edit') }}</x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-ui.card>
@endsection
