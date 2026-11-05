@extends('members::layout')
@section('admin-title', __('members::admin.fee_categories.title'))
@section('members-content')
<x-members::page-header :title="__('members::admin.fee_categories.title')" />

<x-ui.card :title="__('members::admin.fee_categories.new_category')">
    <form method="POST" action="{{ route('members.fee-categories.store') }}" class="mbr-inline-form">
        @csrf
        <x-ui.field class="mbr-inline-form__field" :label="__('members::admin.fee_categories.name')" for="fc-name">
            <x-ui.input name="name" id="fc-name" />
        </x-ui.field>
        <x-ui.button type="submit" :block="false">{{ __('members::admin.common.save') }}</x-ui.button>
    </form>
</x-ui.card>

<x-ui.card :title="__('members::admin.fee_categories.rates_for')">
    <form method="GET" action="{{ route('members.fee-categories.index') }}" class="mbr-inline-form">
        <x-ui.field class="mbr-inline-form__field" :label="__('members::admin.fee_categories.rates_for')" for="fc-fiscal-year">
            <x-ui.select name="fiscal_year_id" id="fc-fiscal-year" :options="$fiscalYears->pluck('name', 'id')->all()" :selected="$selectedFiscalYearId" onchange="this.form.submit()" />
        </x-ui.field>
    </form>

    @if($fiscalYears->isNotEmpty())
        <form method="POST" action="{{ route('members.fee-categories.rates.store') }}" class="mbr-rates-form">
            @csrf
            <input type="hidden" name="fiscal_year_id" value="{{ $selectedFiscalYearId }}">
            <x-ui.table :caption="__('members::admin.fee_categories.rates_for')">
                <x-slot:head>
                    <tr>
                        <th scope="col">{{ __('members::admin.fee_categories.name') }}</th>
                        <th scope="col">{{ __('members::admin.fee_categories.amount') }}</th>
                    </tr>
                </x-slot:head>
                @foreach($categories as $category)
                    <tr>
                        <td class="ds-cell--title" data-label="{{ __('members::admin.fee_categories.name') }}">{{ $category->name }}</td>
                        <td data-label="{{ __('members::admin.fee_categories.amount') }}">
                            <x-ui.input type="number" step="0.01" min="0" name="rates[{{ $category->id }}]" id="fc-rate-{{ $category->id }}" :value="$rates[$category->id]->amount ?? null" aria-label="{{ $category->name }}" />
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
            <div><x-ui.button type="submit" :block="false">{{ __('members::admin.common.save') }}</x-ui.button></div>
        </form>
    @endif
</x-ui.card>
@endsection
